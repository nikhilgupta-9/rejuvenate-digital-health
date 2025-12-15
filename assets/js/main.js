/* ===================================================================
    Author          : Modina Theme
    Version         : 1.0
* ================================================================= */
(function($) {
    "use strict";

    $(document).ready( function() {

        //>> Mobile Menu Js Start <<//
        $('#mobile-menu').meanmenu({
            meanMenuContainer: '.mobile-menu',
            meanScreenWidth: "1199",
            meanExpand: ['<i class="far fa-plus"></i>'],
        });

        //>> Sidebar Toggle Js Start <<//
        $(".offcanvas__close,.offcanvas__overlay").on("click", function() {
            $(".offcanvas__info").removeClass("info-open");
            $(".offcanvas__overlay").removeClass("overlay-open");
        });
        $(".sidebar__toggle").on("click", function() {
            $(".offcanvas__info").addClass("info-open");
            $(".offcanvas__overlay").addClass("overlay-open");
        });

        // Sidebar Area Start <<//
        $(".share-btn").on("click", function() {
            var target = $(this).data("target");
            $("#" + target).toggle();
        });
        $("#openButton").on("click", function(e) {
            e.preventDefault();
            $("#targetElement").removeClass("side_bar_hidden");
        });
        $("#openButton2").on("click", function(e) {
            e.preventDefault();
            $("#targetElement").removeClass("side_bar_hidden");
        });
        $("#closeButton").on("click", function(e) {
            e.preventDefault();
            $("#targetElement").addClass("side_bar_hidden");
        });

        //>> Body Overlay Js Start <<//
        $(".body-overlay").on("click", function() {
            $(".offcanvas__area").removeClass("offcanvas-opened");
            $(".df-search-area").removeClass("opened");;
            $(".body-overlay").removeClass("opened");
        });

        //>> Sticky Header Js Start <<//

       $(window).on('scroll', function() {
            if ($(this).scrollTop() > 250) {
                $("#header-sticky").addClass("sticky");
            } else {
                $("#header-sticky").removeClass("sticky");
            }
        });



        //>> Wow Animation Start <<//
        new WOW().init();

         //>> Video Popup Start <<//
         $(".img-popup").magnificPopup({
            type: "image",
            gallery: {
                enabled: true,
            },
        });

        $('.video-popup').magnificPopup({
            type: 'iframe',
            callbacks: {}
        });


        //>> Nice Select Start <<//
       if ($('.single-select').length) {
            $('.single-select').niceSelect();
        }

        $('.odometer').appear(function(e) {
            var odo = $(".odometer");
            odo.each(function() {
                var countNumber = $(this).attr("data-count");
                $(this).html(countNumber);
            });
        });

        
        if($('.banner-active').length > 0) {
            const bannerActive = new Swiper(".banner-active", {
                speed:1500,
                loop: true,
                slidesPerView: 1,
                effect:'fade',
                autoplay: {
                    delay: 3000,         
                    disableOnInteraction: false,
                    pauseOnMouseEnter: false,  
                },
                navigation: {
                    nextEl: ".array-prev",
                    prevEl: ".array-next",
                },
            });
        }

        //>> Brand Slider Start <<//
        if($('.brand-slide').length > 0) {
            const BrandSlide = new Swiper(".brand-slide", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    1199: {
                        slidesPerView: 6,
                    },
                    991: {
                        slidesPerView: 5,
                    },
                    767: {
                        slidesPerView: 4,
                    },
                    575: {
                        slidesPerView: 3,
                    },
                    400: {
                        slidesPerView: 2,
                    },
                    350: {
                        slidesPerView: 2,
                    },
                },
            });
        }

         //>> Brand Slider-2 Start <<//
        if($('.brand-slide-2').length > 0) {
            const BrandSlide2 = new Swiper(".brand-slide-2", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                     1399: {
                        slidesPerView: 6,
                    },
                    1199: {
                        slidesPerView: 5,
                    },
                    991: {
                        slidesPerView: 4,
                    },
                    767: {
                        slidesPerView: 3,
                    },
                    575: {
                        slidesPerView: 3,
                    },
                    400: {
                        slidesPerView: 2,
                    },
                },
            });
        }

        //>> Project Slider Start <<//
        if($('.project-slide-2').length > 0) {
            const ProjectSlide2 = new Swiper(".project-slide-2", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                 navigation: {
                    nextEl: ".array-prev",
                    prevEl: ".array-next",
                },
                breakpoints: {
                    1199: {
                        slidesPerView: 5,
                    },
                    991: {
                        slidesPerView: 3.1,
                    },
                    767: {
                        slidesPerView: 2.1,
                    },
                    575: {
                        slidesPerView: 1.8,
                    },
                    400: {
                        slidesPerView: 1,
                    },
                },
            });
        } 

         //>> project Slider Start <<//
        if($('.project-slider-3').length > 0) {
            const ProjectSlider3 = new Swiper(".project-slider-3", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                navigation: {
                 nextEl: ".array-prev",
                 prevEl: ".array-next",
               },
                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    1399: {
                        slidesPerView: 5,
                    },
                     1199: {
                        slidesPerView: 4,
                    },
                    991: {
                        slidesPerView: 3,
                    },
                    767: {
                        slidesPerView: 2,
                    },
                    575: {
                        slidesPerView: 1.3,
                    },
                    400: {
                        slidesPerView: 1,
                    },
                },
            });
        } 

        //>> project Slider Start <<//
        if($('.project-slider-5').length > 0) {
            const ProjectSlider5 = new Swiper(".project-slider-5", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                navigation: {
                 nextEl: ".array-prev",
                 prevEl: ".array-next",
               },
                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    1199: {
                        slidesPerView: 3,
                    },
                    991: {
                        slidesPerView: 2,
                    },
                    767: {
                        slidesPerView: 1,
                    },
                    575: {
                        slidesPerView: 1,
                    },
                    400: {
                        slidesPerView: 1,
                    },
                },
            });
        } 

        if($('.testimonial-slider-1').length > 0) {
            const TestimonialSlider1 = new Swiper(".testimonial-slider-1", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                navigation: {
                    nextEl: ".array-prev",
                    prevEl: ".array-next",
               },

                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    1199: {
                        slidesPerView: 3,
                    },
                    991: {
                        slidesPerView: 1,
                    },
                    767: {
                        slidesPerView: 1,
                    },
                    575: {
                        slidesPerView: 1,
                    },
                    400: {
                        slidesPerView: 1,
                    },
                },
            });
        } 

        //>> Testimonial Slider Start <<//
        if($('.testimonial-slider-3').length > 0) {
            const TestimonialSlider3 = new Swiper(".testimonial-slider-3", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                navigation: {
                 nextEl: ".array-prev",
                 prevEl: ".array-next",
               },
                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    1199: {
                        slidesPerView: 1,
                    },
                    991: {
                        slidesPerView: 1,
                    },
                    767: {
                        slidesPerView: 1,
                    },
                    575: {
                        slidesPerView: 1,
                    },
                    400: {
                        slidesPerView: 1,
                    },
                },
            });
        } 
        
        if($('.testimonial-slider-4').length > 0) {
            const TestimonialSlider4 = new Swiper(".testimonial-slider-4", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
               
                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    1199: {
                        slidesPerView: 3,
                    },
                    991: {
                        slidesPerView: 2,
                    },
                    767: {
                        slidesPerView: 2,
                    },
                    575: {
                        slidesPerView: 1,
                    },
                    400: {
                        slidesPerView: 1,
                    },
                },
            });
        } 

        if($('.testimonial-slider-5').length > 0) {
            const TestimonialSlider5 = new Swiper(".testimonial-slider-5", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
               
                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    991: {
                        slidesPerView: 2,
                    },
                    767: {
                        slidesPerView: 1,
                    },
                    575: {
                        slidesPerView: 1,
                    },
                    400: {
                        slidesPerView: 1,
                    },
                },
            });
        } 

        //>> project Slider Start <<//
        if($('.process-slider').length > 0) {
            const ProcessSlider = new Swiper(".process-slider", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                centeredSlides: true,
                navigation: {
                 nextEl: ".array-prev",
                 prevEl: ".array-next",
               },
               pagination: {
                    el: ".process-dot",
                },
                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    1699: {
                        slidesPerView: 4.5,
                    },
                    1599: {
                        slidesPerView: 4.2,
                    },
                    1399: {
                        slidesPerView: 3.7,
                    },
                    1199: {
                        slidesPerView: 3.1,
                    },
                    991: {
                        slidesPerView: 3,
                    },
                    767: {
                        slidesPerView: 2,
                    },
                    575: {
                        slidesPerView: 2,
                    },
                    400: {
                        slidesPerView: 1,
                    },
                },
            });
        } 

        //>> Instagram Slider Start <<//
         const instagramBannerSlider = new Swiper(".instagram-banner-slider", {
            spaceBetween: 30,
            speed: 1500,
            loop: true,
            autoplay: {
                delay: 1000,
                disableOnInteraction: false,
            },
         
            breakpoints: {
                1699: {
                    slidesPerView: 11,
                },
                 1399: {
                    slidesPerView: 9,
                },
                1199: {
                    slidesPerView: 8,
                },
                991: {
                    slidesPerView: 6,
                },
                767: {
                    slidesPerView: 5,
                },
                650: {
                    slidesPerView: 3,
                },
                575: {
                    slidesPerView: 2,
                },
                400: {
                    slidesPerView: 1.5,
                },
                0: {
                    slidesPerView: 1,
                },
            },
        });

         //>> Arrivals Products Slider Start <<//
        if($('.arrivals-products-slider').length > 0) {
            const arrivalsProductsSlider = new Swiper(".arrivals-products-slider", {
                spaceBetween: 30,
                speed: 2000,
                loop: true,
                navigation: {
                 nextEl: ".array-prev",
                 prevEl: ".array-next",
               },
                autoplay: {
                    delay: 1000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    1199: {
                        slidesPerView: 4,
                    },
                    991: {
                        slidesPerView: 3,
                    },
                    767: {
                        slidesPerView: 2,
                    },
                    575: {
                        slidesPerView: 1,
                    },
                    400: {
                        slidesPerView: 1,
                    },
                },
            });
        } 

         //>> News Hover Js Start <<//
            if ($('.news-wrapper-4').length > 0) {
            const getSlide = $('.news-wrapper-4, .news-image-items').length - 1;
            const slideCal = 100 / getSlide + '%';

            $('.news-wrapper-4').css({
                "width": slideCal
            });

            $(document).on('mouseenter', '.news-image-items', function() {
                $('.news-image-items').removeClass('active');
                $(this).addClass('active');
            });
        }
        
        //>> Countdown Js Start <<//

        let targetDate = new Date("2025-12-29 00:00:00").getTime();
        const countdownInterval = setInterval(function () {
            let currentDate = new Date().getTime();
            let remainingTime = targetDate - currentDate;

            if (remainingTime <= 0) {
                clearInterval(countdownInterval);
                // Display a message or perform any action when the countdown timer reaches zero
                $("#countdown-container").text("Countdown has ended!");
            } else {
                let days = Math.floor(remainingTime / (1000 * 60 * 60 * 24));
                let hours = Math.floor(
                    (remainingTime % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)
                );
                let minutes = Math.floor(
                    (remainingTime % (1000 * 60 * 60)) / (1000 * 60)
                );
                let seconds = Math.floor((remainingTime % (1000 * 60)) / 1000);

                // Pad single-digit values with leading zeros
                $("#day").text(days.toString().padStart(2, "0"));
                $("#hour").text(hours.toString().padStart(2, "0"));
                $("#min").text(minutes.toString().padStart(2, "0"));
                $("#sec").text(seconds.toString().padStart(2, "0"));
            }
        }, 1000);

        //>> Sub Title Start <<//
        if($('.tz-sub-tilte').length) {
            var agtsub = $(".tz-sub-tilte");

            if(agtsub.length == 0) return; gsap.registerPlugin(SplitText); agtsub.each(function(index, el) {

                el.split = new SplitText(el, {
                type: "lines,words,chars",
                linesClass: "split-line"
                });

                if( $(el).hasClass('tz-sub-anim') ){
                gsap.set(el.split.chars, {
                    opacity: 0,
                    x: "7",
                });
                }

                el.anim = gsap.to(el.split.chars, {
                scrollTrigger: {
                    trigger: el,
                    start: "top 90%",
                    end: "top 60%",
                    markers: false,
                    scrub: 1,
                },

                x: "0",
                y: "0",
                opacity: 1,
                duration: .7,
                stagger: 0.2,
                });

            });
        }

        //>> Main Title Start <<//
        if($('.tz-itm-title').length) {
            var txtheading = $(".tz-itm-title");

            if(txtheading.length == 0) return; gsap.registerPlugin(SplitText); txtheading.each(function(index, el) {

                el.split = new SplitText(el, {
                type: "lines,words,chars",
                linesClass: "split-line"
                });

                if( $(el).hasClass('tz-itm-anim') ){
                gsap.set(el.split.chars, {
                    opacity: .3,
                    x: "-7",
                });
                }
                el.anim = gsap.to(el.split.chars, {
                scrollTrigger: {
                    trigger: el,
                    start: "top 92%",
                    end: "top 60%",
                    markers: false,
                    scrub: 1,
                },

                x: "0",
                y: "0",
                opacity: 1,
                duration: .7,
                stagger: 0.2,
                });

            });
        }

        //>> Zoom Start <<//
        gsap.utils
        .toArray(".zoom-effect-style")
        .forEach((el, index) => {
            let tl1 = gsap.timeline({
                scrollTrigger: {
                    trigger: el,
                    scrub: 1,
                    start: "top 80%",
                    end: "buttom 60%",
                    toggleActions: "play none none reverse",
                    markers: false,
                },
            });

            tl1.set(el, {
                transformOrigin: "center center"
            }).from(
                el, {
                    scale: 0.7
                }, {
                    background: "inherit",
                    scale: 1,
                    duration: 1,
                    immediateRender: false,
                }
            );
        });

        //>> item_left_1 Start <<//
        gsap.utils.toArray(" .item_left_1").forEach((el, index) => {
            let tlcta = gsap.timeline({
                scrollTrigger: {
                    trigger: el,
                    scrub: 2,
                    start: "top 90%",
                    end: "top 70%",
                    toggleActions: "play none none reverse",
                    markers: false,
                },
            });

            tlcta
                .set(el, {
                    transformOrigin: "center center"
                })
                .from(
                    el, {
                        opacity: 1,
                        x: "-=365"
                    }, {
                        opacity: 1,
                        x: 0,
                        duration: 1,
                        immediateRender: false
                    }
                );
        });

        //>> item_right_1 Start <<//
        gsap.utils.toArray(" .item_right_1").forEach((el, index) => {
            let tlcta = gsap.timeline({
                scrollTrigger: {
                    trigger: el,
                    scrub: 2,
                    start: "top 90%",
                    end: "top 70%",
                    toggleActions: "play none none reverse",
                    markers: false,
                },
            });

            tlcta
                .set(el, {
                    transformOrigin: "center center"
                })
                .from(
                    el, {
                        opacity: 1,
                        x: "+=365"
                    }, {
                        opacity: 1,
                        x: 0,
                        duration: 1,
                        immediateRender: false
                    }
                );
        });

        //>> Shape Animation Start <<//
        const shapeElements = document.querySelectorAll(".suite-bg-shape-1");

        if (shapeElements.length > 0 && typeof gsap !== "undefined" && typeof ScrollTrigger !== "undefined") {
            gsap.registerPlugin(ScrollTrigger);

            shapeElements.forEach(function(el) {
            gsap.timeline({
                scrollTrigger: {
                trigger: el,
                start: "top 80%",
                end: "bottom 10%",
                scrub: 2,
                markers: false,
                }
            }).fromTo(el,
                {
                x: 300,
                },
                {
                x: 0,
                duration: 1.6,
                ease: "power2.out"
                }
            );
            });
        }

        if (window.innerWidth > 768) {
      const items = document.querySelectorAll(".advance-wrap .advance-item");
      if (items.length < 4) return;

      const advanced = gsap.timeline({
        scrollTrigger: {
          trigger: ".advance-wrap",
          start: "top 60%",
          toggleActions: "play none none reverse",
          markers: false,
        },
        defaults: {
          ease: "power1.out", //
          duration: 1,
        },
      });
      advanced
        .from(items[0], { xPercent: 100, rotate: -8 })
        .from(items[1], { xPercent: 30, rotate: 4.13 }, "<")
        .from(items[2], { xPercent: -30, rotate: -6.42 }, "<")
        .from(items[3], { xPercent: -60, rotate: -12.15 }, "<");
  }

     //Cart Increment Decriemnt

        // quntity increment and decrement
        const quantityIncrement = document.querySelectorAll(".quantityIncrement");
        const quantityDecrement = document.querySelectorAll(".quantityDecrement");
        if (quantityIncrement && quantityDecrement) {
            quantityIncrement.forEach((increment) => {
                increment.addEventListener("click", function () {
                    const value = parseInt(
                        increment.parentElement.querySelector("input").value
                    );
                    increment.parentElement.querySelector("input").value = value + 1;
                });
            });

            quantityDecrement.forEach((decrement) => {
                decrement.addEventListener("click", function () {
                    const value = parseInt(
                        decrement.parentElement.querySelector("input").value
                    );
                    if (value > 1) {
                        decrement.parentElement.querySelector("input").value = value - 1;
                    }
                });
            });
        }

        //>> PaymentMethod Js Start <<//
        const paymentMethod = $("input[name='pay-method']:checked").val();
        $(".payment").html(paymentMethod);
        $(".checkout-radio-single").on("click", function() {
            let paymentMethod = $("input[name='pay-method']:checked").val();
            $(".payment").html(paymentMethod);
        });

        //Quantity 
        const inputs = document.querySelectorAll('#qty, #qty2, #qty3');
        const btnminus = document.querySelectorAll('.qtyminus');
        const btnplus = document.querySelectorAll('.qtyplus');

        if (inputs.length > 0 && btnminus.length > 0 && btnplus.length > 0) {

            inputs.forEach(function(input, index) {
                const min = Number(input.getAttribute('min'));
                const max = Number(input.getAttribute('max'));
                const step = Number(input.getAttribute('step'));

                function qtyminus(e) {
                    const current = Number(input.value);
                    const newval = (current - step);
                    if (newval < min) {
                        newval = min;
                    } else if (newval > max) {
                        newval = max;
                    }
                    input.value = Number(newval);
                    e.preventDefault();
                }

                function qtyplus(e) {
                    const current = Number(input.value);
                    const newval = (current + step);
                    if (newval > max) newval = max;
                    input.value = Number(newval);
                    e.preventDefault();
                }

                btnminus[index].addEventListener('click', qtyminus);
                btnplus[index].addEventListener('click', qtyplus);
            });
        }

    // Search bar
        $(".search-toggle").on('click', function(){
        $(".header-search-bar").addClass("search-open");
        $(".offcanvas-overlay").addClass("offcanvas-overlay-open");
        });

    $(".search-close,.offcanvas-overlay").on('click', function(){
        $(".header-search-bar").removeClass("search-open");
        $(".offcanvas-overlay").removeClass("offcanvas-overlay-open");
    });

    //>> Back To Top Slider Start <<//
    $(window).on('scroll', function() {
        if ($(this).scrollTop() > 20) {
            $("#back-top").addClass("show");
        } else {
            $("#back-top").removeClass("show");
        }
    });

    $(document).on('click', '#back-top', function() {
        $('html, body').animate({ scrollTop: 0 }, 800);
        return false;
    });
        
    }); // End Document Ready Function

     //Price Range Slider
    document.addEventListener("DOMContentLoaded", function () {
        const minSlider = document.getElementById("min-slider");
        const maxSlider = document.getElementById("max-slider");
        const amount = document.getElementById("amount");

        function updateAmount() {
            const minValue = parseInt(minSlider.value, 10);
            const maxValue = parseInt(maxSlider.value, 10);

            // Ensure the minimum value is always lower than the maximum value
            if (minValue > maxValue) {
                minSlider.value = maxValue;
            }

            // Update the displayed price range
            amount.value = "$" + minSlider.value + " - $" + maxSlider.value;

            // Calculate the percentage positions of the sliders
            const minPercent =
                ((minSlider.value - minSlider.min) /
                    (minSlider.max - minSlider.min)) *
                100;
            const maxPercent =
                ((maxSlider.value - maxSlider.min) /
                    (maxSlider.max - maxSlider.min)) *
                100;

            // Update the background gradient to show the active track color
            minSlider.style.background = `linear-gradient(to right, #000 ${minPercent}%, #2490EB ${minPercent}%, #2490EB ${maxPercent}%, #000 ${maxPercent}%)`;
            maxSlider.style.background = `linear-gradient(to right, #000 ${minPercent}%, #2490EB ${minPercent}%, #2490EB ${maxPercent}%, #000 ${maxPercent}%)`;
        }

        // Initialize the sliders and track with default values
        amount && updateAmount();

        // if (minSlider && maxSlider) {

        // Add event listeners for both sliders
        minSlider && minSlider.addEventListener("input", updateAmount);
        maxSlider && maxSlider.addEventListener("input", updateAmount);
        // }
    });

    //>> Pre loader Start <<//
    function loader() {
        $(window).on('load', function() {
            // Animate loader off screen
            $(".preloader").addClass('loaded');                    
            $(".preloader").delay(600).fadeOut();                       
        });
    }
    loader();
    
})(jQuery); // End jQuery

