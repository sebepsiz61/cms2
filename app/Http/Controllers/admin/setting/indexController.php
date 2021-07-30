<?php

namespace App\Http\Controllers\admin\setting;
use App\LanguageContent;
use App\Setting;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class indexController extends Controller
{
	public function index()
	{
    $data = Setting::where('id',1)->get();
    return view('admin.setting.index',['data'=>$data]);
	}
	public function update(Request $request)
	{
		$all = $request->except('_token');
		if(isset($all['title']))
		{
			LanguageContent::InsertorUpdate($all['title'], SITE_SETTING_LANGUAGE, TITLE_LANGUAGE, 1);
			unset($all['title']);
		}
		if(isset($all['description']))
		{
			LanguageContent::InsertorUpdate($all['description'], SITE_SETTING_LANGUAGE, DESCRIPTION_LANGUAGE, 1);
			unset($all['description']);
		}
		if(isset($all['keywords']))
		{
			LanguageContent::InsertorUpdate($all['keywords'], SITE_SETTING_LANGUAGE, KEYWORDS_LANGUAGE, 1);
			unset($all['keywords']);
		}
		if(isset($all['footer_text']))
		{
			LanguageContent::InsertorUpdate($all['footer_text'], SITE_SETTING_LANGUAGE, FOOTER_TEXT_LANGUAGE, 1);
			unset($all['footer_text']);
		}
		if(isset($all['banner_title']))
		{
			LanguageContent::InsertorUpdate($all['banner_title'], SITE_SETTING_LANGUAGE, BANNER_TITLE_LANGUAGE, 1);
			unset($all['banner_title']);
		}
		if(isset($all['bannner_description']))
		{
			LanguageContent::InsertorUpdate($all['bannner_description'], SITE_SETTING_LANGUAGE, BANNER_DESCRIPTION_LANGUAGE, 1);
			unset($all['bannner_description']);
		}
		//*  deneme  */				
		if (isset($all['logo']))
			{
				unlink(public_path($all['logo']));
				$array['logo'] = imageHelper::upload(rand(1,9000),"Logo", $all['logo']);
			}

		$update = Referans::where('id', $id)->update($array);

    if (isset($all['logo']))
      {
      LanguageContent::InsertorUpdate($all['logo'], SITE_SETTING_LANGUAGE, IMAGE_LANGUAGE, $id, 1, "Logo");
   	}
	/* deneme */
		$update = Setting::where('id',1)->update($all);
		return redirect()->back()->with('status', 'Veriler Düzenlendi');
	
}
}
