<?php
	//pr_DebugSave('shift_ajax','_POST='.print_r($_POST,true));//@@@

	$asc_ret=array();//出力 初期化
	
	$post_act=$_POST['act'];//actコード（clickBtnTotalExcel）
	
	if($post_act=='clickBtnTotalExcel'){//「登園予定表Excelより取得」ボタン クリック
		$asc_ret=fn_clickBtnTotalExcel();
	}else if($post_act=='clickSave'){//「保存」ボタン クリック
		$asc_ret=fn_clickSave();
	}else{
		$asc_ret['flg_ret']=false;
		$asc_ret['msg_err']='想定外のactコード：'+$post_act;
	}
	
	header("Content-Type: application/json; charset=utf-8");

	echo json_encode($asc_ret);
?>
<?php
	function fn_clickBtnTotalExcel(){//「登園予定表集計」ボタン クリック
		$asc_ret=array();//戻り値 初期化
		//
		$msg_err='';
		$html='';
		$debug='';

		//POST値
		$tta_PasteToenYotei=$_POST['txa_paste_excel'];//Excelから貼り付けたデータ

		//セル位置情報(A1が00)
		$cell_x_year_month=1;$cell_y_year_month=1;//年月
		$cell_x_kid_name=2;$cell_y_kid_name=2;//園児名
		$cell_x_kid_age=2;$cell_y_kid_age=3;//園児歳児
		$cell_x_time_from=2;$cell_y_time_from=5;//登園時刻
		
		//時間帯（30分刻み）
		$asc_time_zone=array(
			 '07:00'=>0,'07:30'=>0,'08:00'=>0,'08:30'=>0,'09:00'=>0,'09:30'=>0,'10:00'=>0,'10:30'=>0
			,'11:00'=>0,'11:30'=>0,'12:00'=>0,'12:30'=>0,'13:00'=>0,'13:30'=>0,'14:00'=>0,'14:30'=>0
			,'15:00'=>0,'15:30'=>0,'16:00'=>0,'16:30'=>0,'17:00'=>0,'17:30'=>0,'18:00'=>0,'18:30'=>0
			,'19:00'=>0,'19:30'=>0,'20:00'=>0,'20:30'=>0
		);

		//集計結果 [日にちidx][歳児][時間帯]=該当園児数 初期化
		$asc_day=array();
		$asc_total_kids=array('0'=>0,'1'=>0,'2'=>0,'3'=>0,'4'=>0,'5'=>0);
		$asc_total_time_zone=array();
		for($d=1;$d<=31;$d++){//日にちidxループ(1日～31日 $dが日にちと不一致の場合あり)
			//時間帯集計用
			$asc_total_time_zone[$d][0]=$asc_time_zone;//0歳児用
			$asc_total_time_zone[$d][1]=$asc_time_zone;//1歳児用
			$asc_total_time_zone[$d][2]=$asc_time_zone;//2歳児用
			$asc_total_time_zone[$d][3]=$asc_time_zone;//3歳児用
			$asc_total_time_zone[$d][4]=$asc_time_zone;//4歳児用
			$asc_total_time_zone[$d][5]=$asc_time_zone;//5歳児用
		}

		//集計処理
		$asc_cell=array();//セルデータ
		$year_month='';//対象年月
		$kid_sn=0;//園児sn

		$ary_row=explode("\n",$tta_PasteToenYotei);//行分割

		//対象年月 記載行
		$ary_col=explode("\t",$ary_row[$cell_y_year_month]);
		$year_month=$ary_col[$cell_x_year_month];//対象年月
		//セルデータ生成
		$y=0;//行カウンタ
		foreach($ary_row as $row){
			$x=0;//列カウンタ
			$ary_col=explode("\t",$row);
			foreach($ary_col as $term){
				$asc_cell[$x][$y]=$term;
				$x++;
			}
			$y++;
		}
//$html.=print_r($asc_cell,true);
		//
		$cur_x_kid_name=$cell_x_kid_name;//園児名位置(2刻み セル結合しているので)
		while(true){
			$kids_name=$asc_cell[$cur_x_kid_name][$cell_y_kid_name];
			if($kids_name==''){
				break;
			}else{
				$age=$asc_cell[$cur_x_kid_name][$cell_y_kid_age];//歳児(0歳～5歳)
				$asc_total_kids[$age]++;//園児数カウントアップ
				//日数ループ
				for($day=1;$day<=31;$day++){//1日～31日ループ
					isset($asc_cell[$cell_x_year_month][$cell_y_time_from+$day-1])?$date=$asc_cell[$cell_x_year_month][$cell_y_time_from+$day-1]:$date='';//日にちセル
					if($date!=''){//日付が空欄で無い
						if(isset($asc_day[$day])==false)$asc_day[$day]=$date;
						$time_from=$asc_cell[$cur_x_kid_name][$cell_y_time_from+$day-1];//登園時刻(+2は「1日」位置を指す)
						$time_to=$asc_cell[$cur_x_kid_name+1][$cell_y_time_from+$day-1];//降園時刻
						//加工
						if(strlen($time_from)==1+1+2)$time_from='0'.$time_from;//時が1桁ならゼロを補う
						if(strlen($time_to)==1+1+2)$time_to='0'.$time_to;//時が1桁ならゼロを補う
						//
						$time_range=$time_from.'-'.$time_to;//「自-至」形式
						list($flg_ok,$asc_total_time_zone)=fc_ConvTimeZone($day,$age,$asc_total_time_zone,$time_range);//「自-至」→集計表累計
						if($flg_ok){
							//NOP:後で出力処理
						}else{
							$msg_err.='<br>'.$kids_name.' '.$date.' '.$time_from.'-'.$time_to;
						}
					}
				}
				//
				$cur_x_kid_name+=2;//2行ごと
			}
		}

		//必要保育士計算
		for($day=1;$day<=31;$day++){
			foreach($asc_time_zone as $time_zone=>$tmp){
				$age0=$asc_total_time_zone[$day][0][$time_zone]/3;//0歳児3人/１保育士
				$age1_2=($asc_total_time_zone[$day][1][$time_zone]+$asc_total_time_zone[$day][2][$time_zone])/6;//1・2歳児6人/１保育士
				$age3=$asc_total_time_zone[$day][3][$time_zone]/20;//3歳児20人/１保育士
				$age4_5=($asc_total_time_zone[$day][4][$time_zone]+$asc_total_time_zone[$day][5][$time_zone])/30;//4・5歳児30人/１保育士
				//
				if($age0+$age1_2+$age3+$age4_5==0){//園児がいない
					$asc_total_time_zone[$day]['num'][$time_zone]=0;//必要保育士数
				}else{
					$age0=floor($age0*10)/10;//小数第2位以下切り捨て
					$age1_2=floor($age1_2*10)/10;//小数第2位以下切り捨て
					$age3=floor($age3*10)/10;//小数第2位以下切り捨て
					$age4_5=floor($age4_5*10)/10;//小数第2位以下切り捨て
					//
					$num=round($age0+$age1_2+$age3+$age4_5)+1;
					//if($num<2)$num=2;
					$asc_total_time_zone[$day]['num'][$time_zone]=$num;//必要保育士数
				}
			}
		}

		//出力処理
		if($msg_err!=''){
			$msg_err='▼エラー<br>'.$msg_err;
		}else{
			$html_btns='';$html_divs='';
			for($day=1;$day<=31;$day++){//1日～31日ループ
				if(isset($asc_day[$day])){
					$date=$asc_day[$day];
					$max_day_hoikushi=0;//必要保育士最大数/日
					//出力用
					$btns='';//liタグ
					$divs='';//divタグ群
					//表示初期化用（初日のボタン色=赤 シフトdiv=表示）
					$style_button='';
					$style_div='';
					if($day==1){
						$style_button='style="background-color:yellow;"';
						$style_div='style="display:bolck;"';
					}else{
						$style_button='style="color:black;background-color:white;"';
						$style_div='style="display:none;"';
					}
					//
					$btns.='<button type="button" '.$style_button.' class="btn_switch_day" id="btn_switch_day'.$day.'">'.$date.'</button>';
					if($day==15)$btns.='<br>';//15日目で次行送り
					$divs.='<div class="div_page_day" '.$style_div.' id="div_page_day'.$day.'">';//日ごとにdivタグで囲む
					$divs.='<table style="margin-top:10px;">';

					$rec_row1='';//タイトル1行目（時部）
					$rec_row2='';//タイトル2行目（分部）
					$rec_row3='';//必要保育士
					//
					$i=0;
					foreach($asc_time_zone as $time_zone=>$tmp){
						if(($i % 2)==0){//偶数なら
							$rec_row1.='<th colspan="2" class="th_row">';
							$rec_row1.=substr($time_zone,0,2);//時部
							$rec_row1.='</th>';
						}
						$rec_row2.='<th class="th_row">';
						$rec_row2.=substr($time_zone,3,2);//分部
						$rec_row2.='</th>';
						//必要保育士数
						$n=$asc_total_time_zone[$day]['num'][$time_zone];
						$style='';if($n>0)$style='style="color:red;"';
						$btn='<button class="btn_need" '.$style.' id="btn_need_'.$day.'_'.fn_removalColon($time_zone).'" value="'.$n.'">'.(-$n).'</button>';
						$rec_row3.='<td>';
						$rec_row3.=$btn;
						$rec_row3.='</td>';
						if($max_day_hoikushi<$asc_total_time_zone[$day]['num'][$time_zone])$max_day_hoikushi=$asc_total_time_zone[$day]['num'][$time_zone];
						//
						$i++;
					}
					$divs.='<tr>';
					$divs.='<th class="th_row" style="background-color:yellow;">'.$date.'</th>';
					$divs.=$rec_row1;
					$divs.='</tr>';
					$divs.='<tr>';
					$divs.='<th class="th_row">歳児</th>';
					$divs.=$rec_row2;
					$divs.='</tr>';

					for($age=0;$age<=5;$age++){
						$divs.='<tr>';
						$divs.='<th class="th_row">';
						$divs.=$age.'歳';
						$divs.='</th>';
						foreach($asc_total_time_zone[$day][$age] as $time_zone=>$cnt_kids){
							$divs.='<td>';
							$divs.=$cnt_kids;
							$divs.='</td>';
						}
						$divs.='</tr>';
					}
					
					//必要保育士
					$divs.='<tr>';
					$divs.='<th class="th_row">';
					$divs.='過不足';
					$divs.='</th>';
					$divs.=$rec_row3;
					$divs.='</tr>';
					
					//オペレーションパネル行
					$rec='';
					$rec.='<tr><td>　</td><td colspan="'.count($asc_time_zone).'">';
					$rec.='<div class="div_mode">モード：';
					$rec.='<label><input type="radio" name="rdo_mode_day'.$day.'" value="normal" checked="checked">通常</label>　';
					$rec.='<label><input type="radio" name="rdo_mode_day'.$day.'" value="9hr">9時間（11:00以前 or 15:00以降）</label>　';
					//$rec.='<label><input type="radio" name="rdo_mode_day'.$day.'" value="rest" disabled="disabled">休憩（未実装）</label>　';
					$rec.='<button class="btn_save">保存</button><span id="spn_saveDatetime"></span>';
					$rec.='</div>';//div_mode
					$rec.='</td></tr>';
					$divs.=$rec;
					
					//保育士シフト部
					$max_day_hoikushi+=3;//必要最大保育士数+3（休憩を考慮）
					$divs.='<input type="hidden" id="hdn_max_day'.$day.'_hoikushi" value="'.$max_day_hoikushi.'">';
					for($no=1;$no<=$max_day_hoikushi;$no++){
						$divs.='<tr class="th_row">';
						$divs.='<th>保育士'.$no.'</th>';
						foreach($asc_time_zone as $time_zone=>$tmp){
							$time_zone=fn_removalColon($time_zone);//コロン除去
							$btn='<button class="btn_shift" id="btn_shift_'.$day.'_'.$time_zone.'_'.$no.'" value="">　</button>';
							$divs.='<td>';
							$divs.=$btn;
							$divs.='</td>';
						}
						$divs.='</tr>';
					}
					
					$divs.='</table>';
					$divs.='</div>';//div_page_day?
					//
					$html_btns.=$btns;
					$html_divs.=$divs;
				}
			}
			//
			$html.=$html_btns;//横並び
			$html.=$html_divs;
		}

		//メッセージ
		if($msg_err==''){
			$asc_ret['result']='OK';
			$asc_ret['div_base']=$html;//集計結果表示エリア（この中に1日から末日までのdivが作成される）
			$asc_ret['debug']=$debug;
		}else{
			$asc_ret['result']='NG';
			$asc_ret['msg']='<span style="color: red">'.$msg_err.'</span>';
			$asc_ret['debug']=$debug;
		}
		//
		return $asc_ret;
	}

	function fn_clickSave(){//「保存」ボタン クリック
		$asc_ret=array();//戻り値 初期化
		//
		$msg_err='';
		$html='';
		$debug='';
		
		//POST値
		$div_base=$_POST['div_base'];
		//DB接続tbl_div_base
		$dbn='mysql:dbname=kadai_06_27;charset=utf8;port=3306;host=localhost';
		$user='root';
		$pwd='';
		try {
			// ここでDB接続処理を実行する
			$pdo=new PDO($dbn,$user,$pwd);
		}catch (PDOException $e) {
			// DB接続に失敗した場合はここでエラーを出力し，以降の処理を中止する
			$msg_err='db_error'.$e->getMessage();
		}

		if($msg_err==''){
			//既存データがなければINSERT、あればUPDATE
			//既存チェック
			$yyyy_mm='2020-06';
			$sql='SELECT COUNT(*) FROM tbl_time_table WHERE yyyy_mm="'.$yyyy_mm.'"';
			$stmt=$pdo->prepare($sql);
			$stmt->bindValue(':yyyy_mm',$yyyy_mm,PDO::PARAM_STR);
			$count=(int)$pdo->query($sql)->fetchColumn();
			
			if($count==0){//既存データがない→INSERT
				$sql='INSERT INTO tbl_time_table(yyyy_mm,div_base) VALUES (:yyyy_mm,:div_base)';
				// SQL準備&実行
				$stmt=$pdo->prepare($sql);
				$stmt->bindValue(':yyyy_mm',$yyyy_mm,PDO::PARAM_STR);//
				$stmt->bindValue(':div_base',$div_base,PDO::PARAM_STR);//
				$status=$stmt->execute();

				if($status==false) {
					// SQL実行に失敗した場合はここでエラーを出力し，以降の処理を中止する
					$msg_err='sqlError:'.$error[2];
				}
			}else{//既存データがない→UPDATE
				$sql='UPADTE tbl_time_table SET div_base=:div_base WHERE yyyy_mm=:yyyy_mm';
				// SQL準備&実行
				$stmt=$pdo->prepare($sql);
				$status=$stmt->execute(array(':yyyy_mm'=>$yyyy_mm,':div_base'=>$div_base));

				if($status==false) {
					 //SQL実行に失敗した場合はここでエラーを出力し，以降の処理を中止する
					$msg_err='sqlError:'.$error[2];
				}
			}
		}

		//メッセージ
		if($msg_err==''){
			$asc_ret['result']='OK';
			$asc_ret['spn_saveDatetime']=date('Y/m/d H:i:s');
			$asc_ret['debug']=$debug;
		}else{
			$asc_ret['result']='NG';
			$asc_ret['msg']='<span style="color: red">'.$msg_err.'</span>';
			$asc_ret['debug']=$debug;
		}
		//
		return $asc_ret;
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//「自-至」→タイムゾーン変換
	//$a_time_range:「時:分-時:分」形式 ※要15分単位
	function fc_ConvTimeZone($a_day,$a_age,$a_asc_total_time_zone,$a_time_range){
		$flg_ok=false;
		$asc_total_time_zone=$a_asc_total_time_zone;
		//
		if($a_time_range=='-'){//登園も降園も空欄時
			$flg_ok=true;//エラーで無い
		}else{
			$ary_time=explode('-',$a_time_range);
			if(Count($ary_time)==2){
				$time_from=$ary_time[0];$time_to=$ary_time[1];
				//時,分が数字2桁判定用
				$time_range=str_replace(':','-',$a_time_range);//デリミタ統一
				$ary_time=explode('-',$time_range);//統一デリミタで分割
				if(Count($ary_time)==4){
					$from_hh=$ary_time[0];$from_mm=$ary_time[1];$to_hh=$ary_time[2];$to_mm=$ary_time[3];
					if((strlen($from_hh)==2)&&(strlen($from_mm)==2)&&(strlen($to_hh)==2)&&(strlen($to_mm)==2)){//時,分 2桁判定
						$from_hh=intval($from_hh);$from_mm=intval($from_mm);$to_hh=intval($to_hh);$to_mm=intval($to_mm);//整数化
						if(($time_from>='07:00')&&($time_from<='20:00')&&($time_to>='07:00')&&($time_to<='20:00')&&($time_from<$time_to)){
							if(($from_mm % 15==0)&&($to_mm % 15==0)){//15分刻み 判定
								$flg_ok=true;//エラーで無い
								//$asc_time_zone 判定ループ
								$flg_belong=false;
								foreach($asc_total_time_zone[$a_day][$a_age] as $time_zone=>$cnt_kids){//時間帯 所属判定ループ zzz:5/17
									if($flg_belong==false){//所属外時
										if($time_zone==$time_from){//所属開始判定
											$flg_belong=true;//所属開始
										}
									}else{//所属中時
										if($time_zone==$time_to){//所属終了判定
											break;//ループ終了
										}
									}
									//
									if($flg_belong){//所属中なら
										$asc_total_time_zone[$a_day][$a_age][$time_zone]=$cnt_kids+1;//カウントアップ
									}
								}
							}
						}
					}
				}
			}
		}
		//
		return array($flg_ok,$asc_total_time_zone);
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	function fn_removalColon($a_str){//コロン除去
		$s_str=$a_str;//戻り値初期化
		//
		$s_str=str_replace(':','',$s_str);
		//
		return $s_str;
	}
	//////////////////////////////////////////////////////////////////////////////////
	//
	//$a_tale:保存ファイル名の末尾付加文字（連番等）
	//$a_text:保存内容
	function pr_DebugSave($a_tale,$a_text){
		$dir='_debug/';//出力先フォルダ
		if(file_exists($dir)==false){
			mkdir($dir,0777,true);//true:再帰作成する
			chmod($dir,0777);
		}
		//
		file_put_contents($dir.date('His').'='.$a_tale.'.txt',$a_text);
	}
	
	/*
	///////////////////////////////////////////////////////////////////////////////////////////////////////////////
	function (){
		$ret=;//戻り値初期化
		//
		//
		return $ret;
	}
	*/
?>
