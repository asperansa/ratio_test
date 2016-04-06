 /**
 * Реализация работы контрола Рейтинг
 */
 
 $(function(){
 
	var ratingWrap=$(".test__rating");

	var star=ratingWrap.find(".rating_star");

	var rating=ratingWrap.find("input[name=rating]");

	/**
	 * Обработчик события клика по звездочке - выбор рейтинга
	 */
	star.click(function(){
	
		var indStar=$(this).index(); /* текущий элемент по счету */

		star.removeClass("marked");
		
		star.each(function(){
		
			if($(this).index()<=indStar){
			
				$(this).addClass("marked");
				
			}
		})

		rating.val(indStar); /* сохраним значение рейтинга */

	})

	/**
	 * Обработчик события наведения на звездочку
	 */
	star.hover(function(){
		
		var indStar=$(this).index(); /* текущий элемент по счету */

		star.each(function(){
		
			if($(this).index()<=indStar){
			
				$(this).addClass("imposed");
				
			}
			
		})
	},
	function(){
	
		star.removeClass("imposed");
		
	})

})