<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Order, Payment};
use Carbon\Carbon;
use DB;

class OrderController extends Controller
{
    public function index() {
    	$orders = Order::where('customer_id', auth()->guard('customer')->user()->id)->orderBy('created_at', 'DESC')->paginate(10);
    	return view('layouts.ecommerce.orders.index', compact('orders'));
    }

    public function view($invoice) {
    	$order = Order::with(['district.city.province', 'details', 'details.product', 'payment'])->where('invoice', $invoice)->first();

        if (Order::where('invoice', $invoice)->exists()) {
            if (\Gate::forUser(auth()->guard('customer')->user())->allows('order-view', $order)) {

                return view('layouts.ecommerce.orders.view', compact('order'));     
            }
        } else {
            return redirect()->back();
        }

    	return redirect(route('customer.orders'))->with(['error' => 'Anda tidak diizinkan untuk mengakses order orang lain.']);
    }

    public function paymentForm() {
    	return view('layouts.ecommerce.payment');
    }

    public function storePayment(Request $request) {
    	$this->validate($request, [
    		'invoice' => 'required|exists:orders,invoice',
	        'name' => 'required|string',
	        'transfer_to' => 'required|string',
	        'transfer_date' => 'required',
	        'amount' => 'required|integer',
	        'proof' => 'required|image|mimes:jpg,png,jpeg'
    	]);

    	DB::beginTransaction();
    	try{ 
    		$order = Order::where('invoice', $request->invoice)->first();

    		if ($order->status == 0 && $request->hasFile('proof')) {
    			$file = $request->file('proof');
    			$filename = time() . '.' . $file->getClientOriginalExtension();
    			$file->storeAs('public/payment', $filename);

    			Payment::create([
    				'order_id' => $order->id,
    				'name' => $request->name,
    				'transfer_to' => $request->transfer_to,
    				'transfer_date' => Carbon::parse($request->transfer_date)->format('Y-m-d'),
    				'amount' => $request->amount,
    				'proof' => $filename,
    				'status' => false
    			]);

    			$order->update(['status' => 1]);

    			DB::commit();

    			return redirect()->back()->with(['success' => 'Pesanan Dikonfirmasi.']);
    		}

    		return redirect()->back()->with(['error' => 'Errpr, upload bukti transfer']);
    	} catch(\Exception $e) {
    		DB::rollback();

    		return redirect()->back()->with(['error' => $e->getMessate()]);
    	}
    }
}