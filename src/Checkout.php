<?php

namespace Yab\ShoppingCart;

use App\Logistics\CartLogistics;
use Yab\ShoppingCart\Models\Cart;
use Yab\ShoppingCart\Models\CartItem;
use Illuminate\Database\Eloquent\Builder;
use Yab\ShoppingCart\Events\CartItemAdded;
use Yab\ShoppingCart\Contracts\Purchaseable;
use Yab\ShoppingCart\Events\CartItemDeleted;
use Yab\ShoppingCart\Events\CartItemUpdated;
use Yab\ShoppingCart\Exceptions\ItemNotPurchaseableException;

class Checkout
{
    /**
     * Create a new checkout instance for a cart.
     *
     * @param \Yab\ShoppingCart\Models\Cart
     */
    public function __construct(protected Cart $cart)
    {
    }

    /**
     * Find a checkout by an existing ID.
     *
     * @param string $checkoutId
     *
     * @return \Yab\ShoppingCart\Checkout
     */
    public static function findById(string $checkoutId) : Checkout
    {
        return new Checkout(Cart::findOrFail($checkoutId));
    }

    /**
     * Create a fresh new checkout with a new ID.
     *
     * @return \Yab\ShoppingCart\Checkout
     */
    public static function create() : Checkout
    {
        return new Checkout(Cart::create());
    }

    /**
     * Destroy this checkout instance and soft delete the checkout.
     *
     * @return void
     */
    public function destroy()
    {
        $this->cart->delete();

        unset($this->cart);
    }

    /**
     * Get the underlying cart model for this checkout instance.
     *
     * @return \Yab\ShoppingCart\Models\Cart
     */
    public function getCart() : Cart
    {
        return $this->cart;
    }

    /**
     * Get the underlying builder instance for the cart.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getCartBuilder() : Builder
    {
        return Cart::whereId($this->cart->id);
    }

    /**
     * Get the purchaseable entity given the purchaseable entity type and ID.
     *
     * @param string $type
     * @param mixed $id
     *
     * @return mixed
     */
    public static function getPurchaseable(string $type, mixed $id) : mixed
    {
        return app(CartLogistics::class)->getPurchaseable($type, $id);
    }

    /**
     * Add an item to the cart.
     *
     * @param mixed $purchaseable
     * @param int $qty
     *
     * @return \Yab\ShoppingCart\Models\CartItem
     */
    public function addItem(mixed $purchaseable, int $qty) : CartItem
    {
        $this->abortIfNotPurchaseable($purchaseable);

        $item = $this->cart->getItem($purchaseable);
        $item->setQty($qty)->calculatePrice()->save();
        
        event(new CartItemAdded($item));

        return $item;
    }

    /**
     * Update an existing item in the cart.
     *
     * @param int $cartItemId
     * @param int $qty
     *
     * @return \Yab\ShoppingCart\Models\CartItem
     */
    public function updateItem(int $cartItemId, int $qty) : CartItem
    {
        $item = CartItem::findOrFail($cartItemId);
        $item->setQty($qty)->calculatePrice()->save();
        
        event(new CartItemUpdated($item));

        return $item;
    }

    /**
     * Remove an existing item from the cart.
     *
     * @param int $cartItemId
     *
     * @return \Yab\ShoppingCart\Models\CartItem
     */
    public function removeItem(int $cartItemId) : CartItem
    {
        $item = CartItem::findOrFail($cartItemId);
        $item->delete();
        
        event(new CartItemDeleted($item));

        return $item;
    }

    /**
     * Get the subtotal for the current cart contents.
     *
     * @return float
     */
    public function getSubtotal() : float
    {
        return round($this->cart->items->sum('price'), 2);
    }

    /**
     * Get the taxes for the current cart contents.
     *
     * @return float
     */
    public function getTaxes() : float
    {
        $taxes = 0;

        $this->cart->items->each(function ($item) use (&$taxes) {
            $taxes += $item->getTaxes();
        });

        return round($taxes, 2);
    }

    /**
     * Get the total for the current cart contents.
     *
     * @return float
     */
    public function getTotal() : float
    {
        return round($this->getSubtotal() + $this->getTaxes(), 2);
    }

    /**
     * Throw an exception if the payload does not implement the purchaseable
     * interface.
     *
     * @param mixed $purchaseable
     *
     * @throws \Yab\ShoppingCart\Exceptions\ItemNotPurchaseableException
     *
     * @return void
     */
    private function abortIfNotPurchaseable(mixed $purchaseable)
    {
        if (!($purchaseable instanceof Purchaseable)) {
            throw new ItemNotPurchaseableException;
        }
    }
}
