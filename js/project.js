jQuery(document).ready(function($){

  'use strict';

  (function() {
    var body = document.body;
    var burgerMenu = document.getElementsByClassName('b-menu')[0];
    var burgerContain = document.getElementsByClassName('b-container')[0];

    burgerMenu.addEventListener('click', function toggleClasses() {
      [body, burgerContain].forEach(function (el) {
        el.classList.toggle('open');
      });
    }, false);
  })();

  $('.menu-open').on('click', function(){
    $('body').toggleClass('menu-panel-isOpen');
  });


  $('.site-logo').click(function(event){
    e.preventDefault();
    $("html, body").animate({ scrollTop: 0 });
  });

});
