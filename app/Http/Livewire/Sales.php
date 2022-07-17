<?php

namespace App\Http\Livewire;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Traits\CartTrait;
use App\Traits\PrinterTrait;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use phpDocumentor\Reflection\Types\This;
use DB;

class Sales extends Component
{
    //traits
    use CartTrait, PrinterTrait;

    // propiedades generales
    public $search, $cash, $searchCustomer, $customer_id =null,  $changes,  $customerSelected ="Seleccionar Cliente";


    // mostrar y activar panels
    public $showListProducts = false, $tabProducts =  true, $tabCategories = false;

    //collections
    public $productsList =[], $customers =[];

    //info del carrito
    public $totalCart = 0, $itemsCart= 0, $contentCart=[];

    // producto seleccionado
    public $productIdSelected, $productChangesSelected, $productNameSelected, $changesProduct;

    protected $paginationTheme = "bootstrap";

    public function render()
    {

        if(strlen($this->searchCustomer) > 0)
            $this->customers =  Customer::where('businame','like',"%{$this->searchCustomer}%")
             ->orderBy('businame','asc')->get()->take(5); //primeros 5 clientes
        else
            $this->customers =  Customer::orderBy('businame','asc')->get()->take(5); //primeros 5 clientes

            $this->totalCart = $this->getTotalCart();
            $this->itemsCart = $this->getItemsCart();
            $this->contentCart = $this->getContentCart();

        return view('livewire.sales.component', [
            'categories' => Category::orderBy('name','asc')->get(),
        ])
        ->layout('layouts.theme.app');
    }


    public function setTabActive($tabName)
    {
        if ($tabName == 'tabProducts') {
            $this->tabProducts = true;
            $this->tabCategories = false;
        }
        else
        {
            $this->tabProducts = false;
            $this->showListProducts = false;
            $this->tabCategories = true;
        }
    }

    public function noty($msg, $eventName= 'noty', )
    {
        $this->dispatchBrowserEvent($eventName, ['msg' => $msg, 'type' => 'success']);
    }

    //operaciones con el carrito
    public function getProductsByCategory($category_id)
    {
        $this->showListProducts =  true;
        $this->productsList = Product::where('category_id', $category_id)->where('stock','>', 0)->get();
    }

    public function add2Cart(Product $product)
    {
       $this->addProductCart($product, 1, $this->changes);
       $this->changes = '';
    }

    public function increaseQty(Product $product, $cant=1)
    {
        $this->updateQtyCart($product, $cant);
    }

    public function decreaseQty($productId)
    {
        $this->decreaseQtyCart($productId);
    }

    public function removeFromCart($id)
    {
        $this->removeProductCart($id);
    }

    public function updateQty(Product $product, $cant=1)
    {
        //para validar si hay las suficientes existencias en bbd y poder vender
        if($cant  + $this->countInCart($product->id) > $product->stock){
            $this->noty('STOCK INSUFICIENTE','noty','error');
            return;
        }
        if ($cant <= 0)
            $this->removeProductCart($product->id);
        else
            $this->replaceQuantityCart($product->id, $cant);
    }



    // para los cambios en el modal
    public function removeChanges()
    {
        $this->clearChanges($this->productIdSelected);
        $this->dispatchBrowserEvent('close-modal-changes'); // evento que va al front para cerrar el modal (a traves de JS)

    }

    public function addChanges($changes)
    {
        $this->addChanges2Product($this->productIdSelected, $changes);
        $this->dispatchBrowserEvent('close-modal-changes');
    }


    public function updatedCustomerSelected()
    {
        $this->dispatchBrowserEvent('close-customer-modal');
    }

    public function resetUI()
    {
        $this->reset('tabProducts', 'cash', 'showListProducts', 'tabCategories', 'search', 'searchCustomer', 'customer_id', 'customerSelected', 'totalCart', 'itemsCart', 'productIdSelected', 'productChangesSelected', 'productNameSelected', 'changesProduct');
    }

    // SAVE SALE //
    public function storeSale($print = false)
    {
        //**********  validamos que haya productos  en la venta */
        if ($this->getTotalCart() <= 0) {
            $this->noty('AGREGA PRODUCTOS A LA VENTA', 'noty', 'error');
            return;
        }


        DB::beginTransaction();

        try {

            // si no se escige cliente , va consumidor final

            if ($this->customerSelected !=  'Seleccionar Cliente') {
                $this->customer_id = Customer::where('businame', $this->customerSelected)->first()->id;
            } else{
                $this->customer_id = Customer::where('businame', 'consumidor final')->first()->id;
            }


            //**********  se graba primero la venta */
            $sale = Order::create([
                'total' => $this->getTotalCart(),
                'shipping' =>  0,
                'items' => $this->getItemsCart(),
                'discount' => 0,
                'cash' => $this->cash,
                'type' => 'Web',
                'status' => 'Pending',
                'user_id' => Auth()->user()->id,
                'customer_id' =>  $this->customer_id
            ]);

            //**********  si hay venta se guarda detalle venta */
            if ($sale) {
                $items = $this->getContentCart();
                foreach ($items as  $item) {
                    OrderDetail::create([
                        'order_id' => $sale->id,
                        'product_id' => $item->id,
                        'quantity' => $item->qty,
                        'price' => $item->price,
                        'changes' => $item->changes
                    ]);

                    //**********  actualizamos stock */
                    $product = Product::find($item->id);
                    $product->stock = $product->stock - $item->qty;
                    $product->save();
                }
            }


            DB::commit();
            $this->clearCart();
            $this->resetUI();

            if ($print) $this->PrintTicket($sale->id);

            $this->noty('VENTA REGISTRADA CON EXITO');


        } catch (\Throwable $e) {
            DB::rollback();
            $this->noty('Error al guardar el pedido: ' . $e->getMessage(), 'noty', 'error');
        }
    }


    public $listeners = ['cancelSale'];

    public function cancelSale()
    {
        $this->clearCart();
        $this->resetUI();
        $this->noty('VENTA CANCELADA');
    }

}
