<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * CSVディレクトリを場所と日付でリンクにするページ
     *
     */
    public function index()
    {
        $mp_libs = new \MpLibs();
        $format_items = $this->get_race_card_csv($mp_libs);

        return view('top', [
            'csv_dir_list' => $format_items
        ]);
        //
        return view('race_card');
    }


    /**
     * 取得したディレクトリを表示用にフォーマット
     * @param object $mplibs
     * @return array $format_dir_list
     */
    private function get_race_card_csv($mplibs)
    {
        $dir_name = $mplibs->get_date_place_list();

        $format_dir_list = [];

        foreach ($dir_name as $dir)
        {
            $format_dir_list[$dir[1]][] = $dir[2];
        }

        return $format_dir_list;
    }
}
