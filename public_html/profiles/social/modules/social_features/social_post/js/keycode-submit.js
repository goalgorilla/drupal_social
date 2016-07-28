/**
* @file
* Handles replacing the visible value of the picked visibility setting.
*/

(function ($) {

'use strict';

  Drupal.behaviors.keycodeSubmit = {
    attach: function (context, settings) {

      var the_form = $('#social-post-entity-form');
      var the_textarea = $('#edit-field-post-0-value');
      var the_submitbutton = $('#edit-submit');

      the_textarea.on("keydown", function(e) {
        if ($.trim(the_textarea.val()) != "") {
          if (e.keyCode == 13 && e.ctrlKey) {
            e.preventDefault();
            the_submitbutton.prop('disabled', true);
            the_form.submit();
          }
        }
      });
    }
  };
})(jQuery);
