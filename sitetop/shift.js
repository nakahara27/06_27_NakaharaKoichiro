		//時間帯（30分刻み）
g_asc_timezone=[
	 '0700','0730','0800','0830','0900','0930','1000','1030'
	,'1100','1130','1200','1230','1300','1330','1400','1430'
	,'1500','1530','1600','1630','1700','1730','1800','1830'
	,'1900','1930','2000','2030'
];

$(function(){
	//呼び出す
	$('#btn_load_db').on('click',function(){
		alert('未実装です');
	});

	//Excel取り込み処理
	$('#btn_total_excel').on('click',function(){
		fn_clickBtnTotalExcel();
	});

	//保存処理
	$(document).on('click','.btn_save',function(){
		fn_clickSave();
	});
	

	
	//日切り替え処理
	$(document).on('click','.btn_switch_day',function(){//zzz
		const btn_id=$(this).attr('id');//btn_switch_day(日No)
		//
		const day=btn_id.replace('btn_switch_day','') ;//「btn_switch_day」除去すると日Noが残る
		const div_id='div_page_day'+day;
		
		//日切り替えボタン処理
		$('.btn_switch_day').css('background-color','white');//いったん全日文字色を黒色
		$('#'+btn_id).css('background-color','yellow');//対象日ボタン文字色を赤色

		//div処理
		$('.div_page_day').css('display','none');//いったん全日を非表示
		$('#'+div_id).css('display','block');//対象日を表示
	});
	
	async function fn_clickBtnTotalExcel(){//「集計」ボタン クリック（非同期（async）指定）-----------
		if(navigator.clipboard){
			const s_clipText=await navigator.clipboard.readText();//クリップボードからテキストを取得（awaitより同期化）
			if(s_clipText){//クリップボード テキストを取得できた時
				$('#txa_paste_excel').val(s_clipText);
				fn_totalExcel();//集計処理
			}else{//クリップボード テキストを取得できない時
				//NOP:何もしない
			}
		}else{
			alert('クリップボード 機能が利用できない環境です');
		}
	}

	function fn_totalExcel(){//Excel取り込み処理------------------------------------------------------
		var asc_post={};
		asc_post['act']='clickBtnTotalExcel';
		asc_post['txa_paste_excel']=$('#txa_paste_excel').val();
		$.ajax({
			url:'shift_ajax.php',
			type:'post',
			dataType:'json',
			data:asc_post,
			timeout:60000,
			cache:false,
			success:function(json_data){
				if(json_data['debug']!='')alert(json_data['debug']);
				if(json_data['result']=='OK'){
					$('#div_base').html(json_data['div_base']);//集計結果表示エリア（この中に1日から末日までのdivが作成される）
				}
			},complete:function(){
				$('#txa_paste_excel').css('display','none');
			},error:function(){
				alert('エラー：ajax呼び出し');
			}
		});
	}

	function fn_clickSave(){//保存処理---------------------------------------------------------------
		var asc_post={};
		asc_post['act']='clickSave';
		asc_post['div_base']=$('#div_base').html();
		$.ajax({
			url:'shift_ajax.php',
			type:'post',
			dataType:'json',
			data:asc_post,
			timeout:60000,
			cache:false,
			success:function(json_data){
				if(json_data['debug']!='')alert(json_data['debug']);
				$('#spn_saveDatetime').html(json_data['spn_saveDatetime']);
			},complete:function(){
				$('#txa_paste_excel').css('display','none');
			},error:function(){
				alert('エラー：ajax呼び出し');
			}
		});
	}

	$(document).on('click','.btn_shift',function(){//btn_shift_(日No)_(時間帯)_(保育士No)
		//クリックされたボタン情報
		const obj_btn_shift=$(this);
		const obj_id_btn_shift=obj_btn_shift.attr('id');
		const ary_btn_shift=obj_id_btn_shift.split('_');//ボタンidを「_」分割
		const rdo_mode=$('input[name=rdo_mode_day'+ary_btn_shift[2]+']:checked').val();//normal/9hr

		//シフト状態遷移
		if(rdo_mode=='normal'){//モード：通常
			if(obj_btn_shift.val()==0){
				obj_btn_shift.css('background-color','lightgreen');
				obj_btn_shift.val('1');
			}else{
				obj_btn_shift.css('background-color','white');
				obj_btn_shift.val('0');
			}
			fn_totalHoikushiByTimezone(obj_id_btn_shift);//必要保育士 過不足 集計

		}else if(rdo_mode=='9hr'){//モード：9時間zzz
			const timezone=ary_btn_shift[3];//時間帯
			
			i_from=1;i_to=1;i_step=1;
			ary_timezone=[];
			if((timezone<='1100')||(timezone>='1500')){//9時間モードが効くのは11:00以前か15:00以降
				if(timezone<='1100'){//11:00以前時→右方向処理
					i_from=$.inArray(timezone,g_asc_timezone);//クリック地点の時間帯配列idx
					i_to=i_from+9*2-1;//9時間帯プラス
					i_step=1;//右方向の意
				}else if(timezone>='1500'){//15:00以降時→左方向処理
					i_from=$.inArray(timezone,g_asc_timezone);//クリック地点の時間帯配列idx
					i_to=i_from-9*2+1;//9時間帯マイナス
					i_step=-1;//左方向の意
				}
				//
				for(i=i_from;i!=i_to;i+=i_step){
					obj_btn=$('#btn_shift_'+ary_btn_shift[2]+'_'+g_asc_timezone[i]+'_'+ary_btn_shift[4]);
					obj_btn.css('background-color','lightgreen');
					obj_btn.val('1');
					fn_totalHoikushiByTimezone(obj_btn.attr('id'));//必要保育士 過不足 集計
				}
			}
			
			//
		}
		
	});

	//必要保育士 過不足 集計
	function fn_totalHoikushiByTimezone(a_btn_shift_id){
		//クリックされたボタン情報
		const ary_btn_shift_id=a_btn_shift_id.split('_');//「_」分割
		const shift_day=ary_btn_shift_id[2];//日No
		const shift_timezone=ary_btn_shift_id[3];//時間帯
		const shift_no=ary_btn_shift_id[4];//保育士No

		//最大保育士No
		const hdn_id_max_day_hoikushi='hdn_max_day'+shift_day+'_hoikushi';
		const max_day_hoikushi=$('#'+hdn_id_max_day_hoikushi).val();//最大保育士No

		//必要保育士数情報
		const btn_id_need='btn_need_'+shift_day+'_'+shift_timezone;//必要保育士数ボタンid文字列
		const obj_btn_need=$('#'+btn_id_need);//必要保育士数ボタンobj
		const btn_need_val=obj_btn_need.val();//valに必要保育士数が記録されている
	
		sum_status=0-btn_need_val;//過不足数初期化（±値）初期値＝マイナス必要保育士数
		for(no=1;no<=max_day_hoikushi-0;no++){//同一日・時間帯の保育士ループ
			btn_id='btn_shift_'+shift_day+'_'+shift_timezone+'_'+no;//保育士btn id名
			shift_status=$('#'+btn_id).val();//シフト状態
			if(shift_status=='1'){//保育シフトに入っていれば
				sum_status++;
			}
		}
		//
		obj_btn_need.html(sum_status);//過不足数 出力
		if(sum_status<0){//不足時
			obj_btn_need.css('color','red');
		}else if(sum_status>0){//過剰時
			obj_btn_need.css('color','blue').html('+'+sum_status);
		}else{//ぴったり時
			obj_btn_need.css('color','lightgreen').html('◎');
		}
	}

	
});
