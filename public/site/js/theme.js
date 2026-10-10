/*-----------------------------------------------------------------------------------
    
    Template Name: Luxivo - Hotel & Resort Booking HTML5 Template
    URI: https://nayonacademy.com/
    Description: Luxivo is a modern, elegant, and fully responsive HTML template designed specifically for hotels, resorts, villas, guest houses, and all kinds of accommodation booking websites. With a clean layout and user-friendly design, Luxivo makes it simple for your customers to explore rooms, check availability, and make reservations with ease.
    Author: Themeservices
    Author URI: https://themeforest.net/user/themeservices
    Version: 1.0 

    Note: This is Main Js file

-----------------------------------------------------------------------------------
    ===================
    Js INDEX
    ===================
    ## Main Menu JS
    ## Offcanvas Overlay JS
    ## Preloader JS
    ## Sticky JS
    ## Back To Top JS
    ## Magnific-popup JS
    ## Slick Slider JS
    ## Counter JS
    ## Isotope JS
    ## Nice Select JS
    ## AOS Js
    ## Background Image JS
    ## Document Ready
    
-----------------------------------------------------------------------------------*/

(function($) {
    'use strict';

    //===== Main Menu

    function mainMenu() {
        var var_window = $(window),
        navContainer = $('.header-navigation'),
        navbarToggler = $('.navbar-toggler'),
        navMenu = $('.theme-nav-menu'),
        navMenuLi = $('.theme-nav-menu ul li ul li'),
        closeIcon = $('.navbar-close');
        navbarToggler.on('click', function() {
            navbarToggler.toggleClass('active');
            navMenu.toggleClass('menu-on');
        });
        closeIcon.on('click', function() {
            navMenu.removeClass('menu-on');
            navbarToggler.removeClass('active');
        });
        navMenu.find("li a").each(function() {
            if ($(this).children('.dd-trigger').length < 1) {
                if ($(this).next().length > 0) {
                    $(this).append('<span class="dd-trigger"><i class="far fa-angle-down"></i></span>')
                }
            }
        });
        navMenu.on('click', '.dd-trigger', function(e) {
            e.preventDefault();
            $(this).parent().parent().siblings().children('ul.sub-menu').slideUp();
            $(this).parent().next('ul.sub-menu').stop(true, true).slideToggle(350);
            $(this).toggleClass('sub-menu-open');
        });
    };

    //===== Offcanvas Overlay

    function offCanvas() {
        const $overlay = $(".offcanvas__overlay");
        const $toggler = $(".navbar-toggler");
        const $menu = $(".theme-nav-menu");
        $toggler.add($overlay).add(".navbar-close, .panel-close-btn").on("click", function () {
            $overlay.toggleClass("overlay-open");
            if ($(this).is($overlay)) {
                $toggler.removeClass("active");
                $menu.removeClass("menu-on");
            }
        });
        $(window).on("resize", function () {
            if ($(window).width() > 991) $overlay.removeClass("overlay-open");
        });
    }

    //===== Windows load

    $(window).on('load', function(event) {
        //===== Preloader
        $('.preloader').delay(500).fadeOut(500);
    })

    //===== Sticky

	$(window).on('scroll', function(event) {
		var scroll = $(window).scrollTop();
		if (scroll < 100) {
			$(".header-navigation").removeClass("sticky");
		} else {
			$(".header-navigation").addClass("sticky");
		}
	});
    
    //===== Back to top

    $(window).on('scroll', function(event) {
        if ($(this).scrollTop() > 600) {
            $('.back-to-top').fadeIn(200)
        } else {
            $('.back-to-top').fadeOut(200)
        }
    });
    $('.back-to-top').on('click', function(event) {
        event.preventDefault();
        $('html, body').animate({
            scrollTop: 0,
        }, 1500);
    });

    //===== Magnific-popup js
    
    if ($('.video-popup').length){
        $('.video-popup').magnificPopup({
            type: 'iframe',
            removalDelay: 300,
            mainClass: 'mfp-fade'
        });
    }
    if ($('.img-popup').length){
            $(".img-popup").magnificPopup({
                type: "image",
                 gallery: { 
                  enabled: true 
                }
            });
        }

    //===== Slick Slider


    if ($('.hero-slider').length) {
        $('.hero-slider').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 800,
            fade: true,
            cssEase: 'cubic-bezier(0.7, 0, 0.3, 1)',
            autoplay: true,
            slidesToShow: 1,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-arrow-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-arrow-right"></i></div>'
        });
    }
    if ($('.hero-image-slider').length) {
        $('.hero-image-slider').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 800,
            fade: true,
            autoplay: true,
            slidesToShow: 1,
            slidesToScroll: 1,
            asNavFor: '.hero-thumb-slider',
            prevArrow: '<div class="prev"><i class="far fa-arrow-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-arrow-right"></i></div>'
        });
    }
    if ($('.hero-thumb-slider').length) {
        var sliderArrows = $('.hero-thumb-arrow');
        $('.hero-thumb-slider').slick({
            dots: false,
            arrows: true,
            infinite: true,
            speed: 800,
            fade: true,
            asNavFor: '.hero-image-slider',
            appendArrows: sliderArrows,
            autoplay: true,
            slidesToShow: 1,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-arrow-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-arrow-right"></i></div>'
        });
    }
    if ($('.room-slider').length) {
        var sliderArrows = $('.room-arrows');
        $('.room-slider').slick({
            dots: false,
            arrows: true,
            infinite: true,
            speed: 800,
            appendArrows: sliderArrows,
            autoplay: true,
            slidesToShow: 4,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-arrow-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-arrow-right"></i></div>',
            responsive: [
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 3,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 2,
                    }
                },
				{
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 1,
                        variableWidth: false,
                    }
                }
            ]
        });
    }
    if ($('.room-slider-two').length) {
        $('.room-slider-two').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 800,
            autoplay: true,
            slidesToShow: 4,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-angle-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-angle-right"></i></div>',
            responsive: [
                {
                    breakpoint: 1450,
                    settings: {
                        slidesToShow: 3,
                    }
                },
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 2,
                    }
                },
				{
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 1,
                        variableWidth: false,
                    }
                }
            ]
        });
    }
    if ($('.room-slider-three').length) {
        $('.room-slider-three').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 800,
            autoplay: true,
            slidesToShow: 3,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-angle-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-angle-right"></i></div>',
            responsive: [
                {
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 2,
                    }
                },
				{
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 1,
                        variableWidth: false,
                    }
                }
            ]
        });
    }
    if ($('.features-slider').length) {
        var sliderArrows = $('.features-arrows');
        $('.features-slider').slick({
            dots: false,
            arrows: true,
            infinite: true,
            speed: 800,
            appendArrows: sliderArrows,
            autoplay: true,
            slidesToShow: 4,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-arrow-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-arrow-right"></i></div>',
            responsive: [
                {
                    breakpoint: 1450,
                    settings: {
                        slidesToShow: 3,
                    }
                },
                {
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 2,
                    }
                },
				{
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 1,
                        variableWidth: false,
                    }
                }
            ]
        });
    }
    if ($('.features-slider-two').length) {
        $('.features-slider-two').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 800,
            autoplay: true,
            slidesToShow: 3,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-angle-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-angle-right"></i></div>',
            responsive: [
                {
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 2,
                    }
                },
				{
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 1,
                        variableWidth: false,
                    }
                }
            ]
        });
    }
    if ($('.testimonial-slider').length) {
        $('.testimonial-slider').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 800,
            autoplay: true,
            slidesToShow: 2,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-arrow-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-arrow-right"></i></div>',
            responsive: [
                {
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 1
                    }
                }
            ]
        });
    }
    if ($('.testimonial-slider-two').length) {
        var sliderArrows = $('.testimonial-arrows');
        $('.testimonial-slider-two').slick({
            dots: false,
            arrows: true,
            infinite: true,
            speed: 800,
            autoplay: true,
            appendArrows: sliderArrows,
            slidesToShow: 4,
            slidesToScroll: 1,
            prevArrow: '<div class="prev">Previews</div>',
            nextArrow: '<div class="next">Next</div>',
            responsive: [
                {
                    breakpoint: 1450,
                    settings: {
                        slidesToShow: 3,
                    }
                },
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 2,
                    }
                },
				{
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 1,
                        variableWidth: false,
                    }
                }
            ]
        });
    }
    if ($('.testimonial-slider-three').length) {
        $('.testimonial-slider-three').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 800,
            autoplay: true,
            slidesToShow: 2,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-arrow-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-arrow-right"></i></div>',
            responsive: [
                {
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 1
                    }
                }
            ]
        });
    }
    $('.testimonial-slider-two').on('init afterChange', function(event, slick, currentSlide){
        currentSlide = currentSlide || 0;
        const totalSlides = slick.slideCount;
        const progressLine = $('.progress-line');
        let progress = ((currentSlide + 1) / totalSlides) * 100;
        progressLine.css('width', progress + '%');
    });
    if ($('.gallery-slider').length) {
        $('.gallery-slider').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 800,
            autoplay: true,
            slidesToShow: 6,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-angle-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-angle-right"></i></div>',
            responsive: [
                {
                    breakpoint: 1450,
                    settings: {
                        slidesToShow: 5,
                    }
                },
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 3,
                    }
                },
				{
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 2
                    }
                },
                {
                    breakpoint: 600,
                    settings: {
                        slidesToShow: 1
                    }
                }
            ]
        });
    }
    if ($('.gallery-slider-two').length) {
        $('.gallery-slider-two').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 800,
            autoplay: true,
            slidesToShow: 3,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-angle-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-angle-right"></i></div>',
            responsive: [
				{
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 2
                    }
                },
                {
                    breakpoint: 400,
                    settings: {
                        slidesToShow: 1
                    }
                }
            ]
        });
    }
    if ($('.room-gallery-slider').length) {
        $('.room-gallery-slider').slick({
            dots: false,
            arrows: false,
            infinite: true,
            speed: 800,
            autoplay: true,
            slidesToShow: 2,
            slidesToScroll: 1,
            prevArrow: '<div class="prev"><i class="far fa-angle-left"></i></div>',
            nextArrow: '<div class="next"><i class="far fa-angle-right"></i></div>',
            responsive: [
				{
                    breakpoint: 600,
                    settings: {
                        slidesToShow: 1
                    }
                }
            ]
        });
    }
    // ===== Counter

	if ($('.counter').length) {
		const observer = new IntersectionObserver((entries, observer) => {
			entries.forEach(entry => {
				if (entry.isIntersecting) {
					$(entry.target).counterUp({
						delay: 100,
						time: 4000
					});
					observer.unobserve(entry.target);
				}
			});
		}, {
			threshold: 1.0
		});
		$('.counter').each(function() {
			observer.observe(this);
		});
	}

    //====== Isotope js

    $('.filter-nav-items li').on('click', function () {
        $(this).siblings('.active').removeClass('active');
        $(this).addClass('active');
        var filterValue = $(this).attr('data-filter');
        $('.isotope-grid').isotope({ filter: filterValue });
    });

    if ($('.luxivo-isotope').length) {
        $('.luxivo-isotope').imagesLoaded(function () {
            $('.isotope-grid').isotope({
                itemSelector: '.isotope-filter-item',
                layoutMode: 'fitRows'
            })
        });
    }


    //===== Nice select js
    
    if ($('select').length){
        $('select').niceSelect();
    }

    //====== Aos 
    
    AOS.init({
        offset: 0,
        mirror: true,
    });

    //===== Dynamic Background

    function dynamicBackground() {
        $('[data-src]').each(function () {
          var src = $(this).attr('data-src');
          $(this).css({
            'background-image': 'url(' + src + ')',
          });
        });
    }

    //===== Document Ready

    $(function() {
        mainMenu();
        offCanvas();
        dynamicBackground();
    });

})(window.jQuery);