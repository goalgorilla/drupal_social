jQuery(document).ready(function($){

  'use strict';

  $('.menu-open').on('click', function(e){
    $('body').toggleClass('menu-panel-isOpen');
    e.preventDefault();
  });

  $('.b-menu').on('click', function(e) {
    $('.b-container').toggleClass('open');
    e.preventDefault();
  });


  $('.site-logo').click(function(event){
    e.preventDefault();
    $("html, body").animate({ scrollTop: 0 });
  });

});
