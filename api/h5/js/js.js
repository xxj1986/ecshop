/*成功*/
function xszq(){
	$("html").addClass("overflow");
	$("body").addClass("overflow");
	$(".tuank_1").show();
	$(".tuank1_1").css("margin-top",- $(".tuank1_1").height() / 2);
	$(".gb1").click(function(){
		$("html").removeClass("overflow");
		$("body").removeClass("overflow");
		$(".tuank_1").hide();
	})
}
/*失败*/
function xssb(){
	$("html").addClass("overflow");
	$("body").addClass("overflow");
	$(".tuank_2").show();
	$(".tuank2_1").css("margin-top",- $(".tuank2_1").height() / 2);
	$(".gb2").click(function(){
		$("html").removeClass("overflow");
		$("body").removeClass("overflow");
		$(".tuank_2").hide();
	})
}