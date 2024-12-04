<?php

namespace App\Libs;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Storage;
use App\Models\StallionCorse;
use App\Models\StallionBias;

class MpLibs extends Facade
{
    /**
     * Inputに入っているディレクトリリストを取得（日付ソート）
     * @return array $csv_da_place_list
     */
    public function get_date_place_list()
    {
        $dir_list = $this->get_csv_dir('csv');
        $csv_da_place_list = [];
        $i = 0;
        foreach ($dir_list as $dir_names)
        {
            $dir_explode = explode('/', $dir_names);
            if (count($dir_explode) == 3)
            {
                $csv_da_place_list[$i] =  $dir_explode;
                $sort[$i] = $dir_explode[1];
                $i++;
            }
        }
        array_multisort($sort, SORT_DESC, $csv_da_place_list);
        return $csv_da_place_list;
    }

    /**
     * Storage/app の中のディレクトリリストを取得
     * @param string $dir_name 対象ディレクトリ名
     * @return array $dir_list
     */
    public function get_csv_dir($dir_name)
    {
        //$dir_list = Storage::directories($dir_name);
        $dir_list = Storage::allDirectories($dir_name);
        return $dir_list;
    }
    
    /**
     * Storage/app/csv の中の指定CSVを取得
     * @param int $target_date
     * @param string $place
     * @param string $csv_name
     * @return array csv_data
     */
    public function get_csv($target_date, $place, $csv_name)
    {
        // todo 引数のバリデーション
        $filepath = \MpConsts::MP_CSV_PATH_BASE . $target_date . '/' . $place . '/' . $csv_name;
        
        if (!file_exists($filepath)) return "";
        
        // CSV取得
        $file = new \SplFileObject($filepath);
        $file->setFlags(
            \SplFileObject::READ_CSV | 
            \SplFileObject::READ_AHEAD |
            \SplFileObject::SKIP_EMPTY |
            \SplFileObject::DROP_NEW_LINE 
        );

        return $file;
    }

    /**
     * 指定日の$target_befoer_dayの数値分前の日付を返却
     * @param int $date
     * @return formated_date デフォルト2週間前の日付
     */
    public function format_date($date, $target_befoer_day = 14)
    {
        $befoer_day = -$target_befoer_day . ' day';
        return date("Ymd", strtotime($date. $befoer_day));
    }

    /**
     * trackの文字列からトラックコードに変換
     * @param string $track 
     * @return int track_code
     */
    public function convert_track_type($track)
    {
        if ($track == '芝')
        {
            return 1;
        }
        else if ($track == 'ダート')
        {
            return 2;
        }
        else if ($track == '障害・芝')
        {
            return 3;
        }
        else if ($track == '障害・ダ')
        {
            return 4;
        }
    }

    /**
     * 種牡馬ポイントを返却
     * @param int $stallion_id
     * @param int $corse_id
     * @param string $track
     * @param array $bias
     * @param float $cushion
     * @return int 
     */
    public function get_stallion_point($stallion_id, $corse_id, $track, $bias, $cushion)
    {
        $track_code = $this->convert_track_type($track);        
        $point = 0;
        $bias_key_name = $this->_get_bias_key_name($track_code, $bias);
        $stallion_bias = StallionBias::where('stallion_id', $stallion_id)->get()->toArray();
        $stallion_corse = StallionCorse::where('stallion_id', $stallion_id)->get()->toArray();

        if (!empty($stallion_bias))
        {
            $stallion_bias = array_column($stallion_bias, 'point', 'bias_type');
            // biasのポイント取得
            $point += isset($stallion_bias[$bias_key_name]) ? $stallion_bias[$bias_key_name] : 0;
            // 芝ならクッションポイントを取る
            if ($track_code != 2)
            {
                $point += isset($stallion_bias[$this->get_cushion_range($cushion)]) ? $stallion_bias[$this->get_cushion_range($cushion)] : 0;
            }
        }

        if (!empty($stallion_corse))
        {
            $stallion_corse = array_column($stallion_corse, 'point', 'corse_id');
            $point += isset($stallion_corse[$corse_id]) ? $stallion_corse[$corse_id] : 0;
        }
        return $point;
    }

    /**
     * stallion_biasに登録しているバイアスキーを状態から作る
     * @param int $track_code 
     * @param array $bias 
     * @return int 
     */
    private function _get_bias_key_name($track_code, $bias)
    {
        $key_name = "2". $bias[$track_code];
        if ($track_code == 1 || $track_code == 3)
        {
            $key_name = "1". $bias[$track_code];
        }
        return (int)$key_name;
    }

    const POINT_LOOP_COUNT = 4;
    /**
     * 計算した各ポイントのリストから総合ポイント的なやつを算出する
     * @param array $race_card
     * @return array $race_card 総合ポイントを入れたカードリスト
     */
    public function set_race_mark_point($race_card)
    {
        $mark_point = [];
        foreach($race_card as $no => $card)
        {
            $point_list = [
                'same_course_race_point' => $card['same_course_race_point']['point'],
                'same_distance_point' => $card['same_distance_point']['point'],
                'same_pace_point' => $card['same_pace_point']['point'],
                'cushion_pace_point' => $card['cushion_pace_point']['point'],
                'track_bias_race_point' => $card['track_bias_race_point']['point'],
                'corner_type_race_point' => $card['corner_type_race_point']['point'],
                'moisture_content' => $card['moisture_content']['point'],
            ];
            arsort($point_list);

            // ベースとなるポイントを抽出（point or previous_race_point or point_all）
            $base_point = max($card['point']['point'], $card['previous_race_point']['point'], $card['point_all']['point']);
            //$base_point = max($card['point']['point'], $card['previous_race_point']['point']);
            //$base_point = current($point_list);
            $point = $base_point;
            $i = 0;
            foreach ($point_list as $point_key => $val)
            {
                $point += $this->_set_add_point($base_point, $card[$point_key]['point'], $card[$point_key]['rank']);
                $i++;
                if ($i == self::POINT_LOOP_COUNT) break;
            }

/*
            // 同競馬場ポイント足し込み same_course_race_point
            $point += $this->_set_add_point($base_point, $card['same_course_race_point']['point'], $card['same_course_race_point']['rank']);
            // 同距離ポイント足し込み same_distance_point
            $point += $this->_set_add_point($base_point, $card['same_distance_point']['point'], $card['same_distance_point']['rank']);
            // 同ペースポイント足し込み same_pace_point
            $point += $this->_set_add_point($base_point, $card['same_pace_point']['point'], $card['same_pace_point']['rank']);
            // 同クッションポイント足し込み cushion_pace_point
            $point += $this->_set_add_point($base_point, $card['cushion_pace_point']['point'], $card['cushion_pace_point']['rank']);
            // 同バイアスポイント足し込み track_bias_race_point
            $point += $this->_set_add_point($base_point, $card['track_bias_race_point']['point'], $card['track_bias_race_point']['rank']);
            // 同コーナータイプポイント足し込み corner_type_race_point
            $point += $this->_set_add_point($base_point, $card['corner_type_race_point']['point'], $card['corner_type_race_point']['rank']);
            // 同含水率ポイント足し込み moisture_content
            $point += $this->_set_add_point($base_point, $card['moisture_content']['point'], $card['moisture_content']['rank']);
  */          
            
            // 調教値を足し込み hanro_point wood_point
            $point += $this->_set_add_chokyo_point($card['hanro_point'], $card['wood_point']);
            // ジョッキーランクを足し込む jockey_rank
            $point += $card['jockey_rank'];
            // 初ブリンカー
            $point += ($card["mark_6"] == "初") ? 5 : 0;
            // 斤体比を計算してポイント化
            $point += $this->_set_add_weight_point($card['hande_weight_per']);

            // 得意種牡馬でポイント加算（todo 未実装）

            // パドックポイント加算（todo 未実装)
            
             
            $race_card[$no]['no'] = $no;
            $race_card[$no]['race_mark_point'] = $point;
        }

        $rank = $this->sort_by_key('race_mark_point', SORT_DESC, $race_card);
        foreach ($rank as $k => $v)
        {
            $race_card[$v['no']]['race_mark_point'] = ['point' => $v['race_mark_point'], 'rank' => $k +1];
        }

        return $race_card;
    }

    /**
     * レースクッション値から計算できるように幅による定数をはめる
     * @param float $cushion
     * @return int 幅に使用するクッションレンジX１０
     * 使用するときは１０で割る
     */
    public function get_cushion_range($cushion)
    {
        if (!$cushion)
        {
            return 0;
        }
        // 小数点はキーに出来ないので１０倍。表示で１０で割る
        if ($cushion <= 7.2)
        {
            return 70;
        }
        else if ($cushion > 7.2 && $cushion <= 7.7)
        {
            return 75;
        }
        else if ($cushion > 7.7 && $cushion <= 8.2)
        {
            return 80;
        }
        else if ($cushion > 8.2 && $cushion <= 8.7)
        {
            return 85;
        }
        else if ($cushion > 8.7 && $cushion <= 9.2)
        {
            return 90;
        }
        else if ($cushion > 9.2 && $cushion <= 9.7)
        {
            return 95;
        }
        else if ($cushion > 9.7 && $cushion <= 10.2)
        {
            return 100;
        }
        else if ($cushion > 10.2 && $cushion <= 10.7)
        {
            return 105;
        }
        else if ($cushion > 10.7)
        {
            return 110;
        }
        else
        {
            return 0;
        }
    }

    /**
     * 斤体比でポイント化
     * @param float $hande_weight_per 斤体比
     * @return int 足されるポイント
     */
    private function _set_add_weight_point($hande_weight_per)
    {
        $add_point = 0;
        if ($hande_weight_per < 10.5)
        {
            $add_point += 5;
        }
        else if ($hande_weight_per < 11 && $hande_weight_per >= 10.5)
        {
            $add_point += 4;
        }
        else if ($hande_weight_per < 11.5 && $hande_weight_per >= 11)
        {
            $add_point += 3;
        }
        else if ($hande_weight_per < 12 && $hande_weight_per >= 11.5)
        {
            $add_point += 3;
        }

        else if ($hande_weight_per < 12.5 && $hande_weight_per >= 12)
        {
            $add_point += 1;
        }
        else if ($hande_weight_per < 13 && $hande_weight_per >= 12.5)
        {
            $add_point += 1;
        }
        else
        {
            $add_point += 0;
        }

        return $add_point;
    }

    /**
     * 調教によるポイント加算
     * @param int $hanro
     * @param int $wood
     * @return int 加算されるポイント数
     */
    private function _set_add_chokyo_point($hanro, $wood)
    {
        $add_point = 0;
        $max = max($hanro, $wood);
        if ($max >= 75)
        {
            $add_point += 10;
        }
        else if ($max < 75 && $max >= 70)
        {
            $add_point += 7;
        }
        else if ($max < 70 && $max >= 65)
        {
            $add_point += 5;
        }
        else if ($max < 65 && $max >= 60)
        {
            $add_point += 3;
        }
        else if ($max < 60 && $max >= 55)
        {
            $add_point += 1;
        }

        // 両方良い場合のプラス査定
        if ($hanro >= 75 && $wood >= 75)
        {
            $add_point += 5;
        }
        else if ($hanro >= 70 && $wood >= 70)
        {
            $add_point += 4;
        }
        else if ($hanro >= 65 && $wood >= 65)
        {
            $add_point += 3;
        }
        else if ($hanro >= 60 && $wood >= 60)
        {
            $add_point += 2;
        }
        return $add_point;
    }

    /**
     * ベースポイントとの比較でポイント加算したりランクでポイントつけたりする
     * @param int $base_point
     * @param int $target_point
     * @param int $rank
     * @return int 加算後ポイント
     */
    private function _set_add_point($base_point, $target_point, $rank)
    {
        $add_base_point = 5;
        $diff_point = $target_point - $base_point;

        // 未経験条件の可能性あり
        if ($target_point <= 50 )
        {
            return $add_base_point;
        } 

        if ($diff_point >= 10) 
        {
            $add_base_point += 3;
        }
        else if ($diff_point >= 7 && $diff_point < 10) 
        {
            $add_base_point += 2.5;
        }
        else if ($diff_point >= 4 && $diff_point < 7) 
        {
            $add_base_point += 2;
        }
        else if ($diff_point >= 2 && $diff_point < 4) 
        {
            $add_base_point += 1.5;
        }
        else if ($diff_point >= 0 && $diff_point < 2) 
        {
            $add_base_point += 0.5;
        }
        else if ($diff_point >= -2 && $diff_point < 0) 
        {
            $add_base_point += 0;
        }
        else if ($diff_point >= -2 && $diff_point < 0) 
        {
            $add_base_point -= 0;
        }
        else if ($diff_point >= -4 && $diff_point < -2) 
        {
            $add_base_point -= 1;
        }
        else if ($diff_point >= -7 && $diff_point < -4) 
        {
            $add_base_point -= 2;
        }
        else if ($diff_point >= -10 && $diff_point < -7) 
        {
            $add_base_point -= 3;
        }
        else if ($diff_point < -10) 
        {
            $add_base_point -= 3;
        }

        if ($rank == 1)
        {
            $add_base_point += 5;
        }
        else if ($rank == 2)
        {
            $add_base_point += 4;
        }
        else if ($rank == 3)
        {
            $add_base_point += 3;
        }
        else if ($rank == 4)
        {
            $add_base_point += 2;
        }
        else if ($rank == 5)
        {
            $add_base_point += 1;
        }

        return $add_base_point;
    }

    /**
     * 調教の加速マークを判定
     * 
     * @param array $hanro 坂路１位の結果
     * @param array $hanro ウッド１位の結果
     * @return string 判定後のマーク（↑↑　↑　‐　↓　↓↓）
     */
    public function get_kasoku_mark($hanro, $wood)
    {
        $hanro_point = isset($hanro->point) ? $hanro->point : 0;
        $wood_point = isset($wood->point) ? $wood->point : 0;

        $chokyo_type = 1; // hanro
        if ($hanro_point < $wood_point)
        {
            $chokyo_type = 2; // wood
        }

        if ($chokyo_type == 1) 
        {
            $lap_2 = isset($hanro->lap_2) ? (float)$hanro->lap_2 : 0; 
            $lap_1 = isset($hanro->lap_1)  ? (float)$hanro->lap_1 : 0;
            $kasoku_time = $lap_2 - $lap_1;

            if ($kasoku_time >= 0.6)
            {
                $return_string = '↑↑';
            }
            else if ($kasoku_time >= 0.3 && $kasoku_time < 0.6)
            {
                $return_string = '↑';
            }
            else if ($kasoku_time >= -0.2 && $kasoku_time < 0.3)
            {
                $return_string = '-';
            }
            else if ($kasoku_time <= -0.2 && $kasoku_time > -0.6)
            {
                $return_string = '↓';
            }
            else if ($kasoku_time <= -0.6)
            {
                $return_string = '↓↓';
            }
            else
            {
                $return_string = '-';
            }
        }
        else
        {
            $lap_2 = isset($wood->lap_2) ? (float)$wood->lap_2 : 0; 
            $lap_1 = isset($wood->lap_1)  ? (float)$wood->lap_1 : 0;
            $kasoku_time = $lap_2 - $lap_1;

            if ($kasoku_time >= 1.2)
            {
                $return_string = '↑↑';
            }
            else if ($kasoku_time >= 0.5 && $kasoku_time < 1.2)
            {
                $return_string = '↑';
            }
            else if ($kasoku_time >= -0.2 && $kasoku_time < 0.5)
            {
                $return_string = '-';
            }
            else if ($kasoku_time <= -0.2 && $kasoku_time > -0.6)
            {
                $return_string = '↓';
            }
            else if ($kasoku_time <= -0.6)
            {
                $return_string = '↓↓';
            }
            else
            {
                $return_string = '-';
            }
        }

        return $return_string;
    }

    /**
	 * 連想配列の指定キーのValueでソートする(ポイント等のランク付け)
	 * @param string $key_name ソート対象のキー名
	 * @param string $sort_order　SORT_ASC　SORT_DESC
	 * @param array $array 1次元の連想配列
	 * @return array
	 */
	public function sort_by_key($key_name, $sort_order, $array)
	{
	    foreach ($array as $key => $value)
	    {
	        $standard_key_array[$key] = $value[$key_name];
	    }
	    array_multisort($standard_key_array, $sort_order, $array);
	    return $array;
	}
}
