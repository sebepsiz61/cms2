<?php

namespace App\Http\Controllers\front\contact;

use Mail;
use Log;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class indexController extends Controller
{
    public function index()
    {
    	return view('front.contact.index');
    }
    public function store(Request $request)
    {
    	$request->validate(['name'=>'required','email'=>'required','message'=>'required']);
    	$all = $request->except('_token');

    	$data = ['name'=>$all['name'],'email'=>$all['email'],'text'=>$all['message']];

       	try {

    	Mail::send('mail.contact', $data, function ($message)
    	{
    		$message->subject('İletişim ');
    		$message->to(SYSTEM_EMAIL);
    	});
    	return redirect()->back()->with('swal',trans('general.contact_form_success'));
    }
    catch (\Exception $e)
    {
    	Log::info($e->getMessage());
    	return redirect()->back()->with('swal',trans('general.contact_form_alert'));
    } 
    
		
    }
}
