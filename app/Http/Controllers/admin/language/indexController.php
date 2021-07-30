<?php

namespace App\Http\Controllers\admin\language;

use Yajra\DataTables\DataTables;
use App\Language;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class indexController extends Controller
{
     public function index()
    {
        return view ('admin.language.index');
    }
    public function create()
    {
    	return view('admin.language.create');
    }
    public function edit($id)
    {
        $c = Language::where('id',$id)->count();
        if ($c!=0)
        {
         $data = Language::where('id',$id)->get();
         return view('admin.language.edit',['data'=>$data]);   
        }
        else
        {
            return abort(404);
        }
    }

    public function update(Request $request)
    {
        $id = $request->route('id');
        $all = $request->except('_token');
        Language::where('id',$id)->update($all);
        return redirect()->back()->with('status', 'Dil Güncellendi.');
    }

    public function store(Request $request)
    {
    	$request->validate(['name'=>'required', 'code'=>'required']);
    	$all = $request->except('_token');

    	$insert = language::create($all);
    	if($insert)
    	{
    		return redirect()->back()->with('status', 'Başarıyla Eklendi');
    	}else
    	{
    		return redirect()->back()->with('status', 'Hata Eklenemedi');
    	} 
    }
     public function data(Request $request)
   {
    $query = Language::query();
    $data = DataTables::of($query)
  
        ->addColumn('edit', function($query)
        {
        return '<a href="'.route('admin.language.edit',['id'=>$query->id]).'">Düzenle</a>'; 
        })
        ->addColumn('delete', function($query)
        {
        return '<a href="'.route('admin.language.delete',['id'=>$query->id]).'">Sil</a>';   
        })
        ->rawColumns(['edit','delete'])
            ->make(true);

        return $data;
   }
   public function delete($id)
    {
        $all = Language::where('id',$id)->delete();
        return redirect()->back()->with('status', 'Başarıyla Eklendi');
    }
      
}

