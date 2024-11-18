jQuery(document).ready(function($) {
  var shown;
  $('a.show_change_tier_uuid').on('click', function() {
    var postID = $(this).attr('wicket-tier-id');
    var div = 'tier_div_'+postID;
      if(shown === true) {
        document.getElementById(div).style.display="none";
      } else {
        document.getElementById(div).style.display="block";
      }
      shown = !shown;
  });

  $('.wicket_update_tier_uuid').on('click', function(event) {
        event.preventDefault();
        var tierNonce = $(this).attr('data-nonce')
        var postID = $(this).attr('wicket-tier-post-id')
        tierUUID = $('#tier_post_'+postID).val();
        //console.log([tierUUID, postID]);
        $.ajax({
          url: ajax_object.ajax_url,
          method: 'POST',
          data: {
              action: 'wicket_tier_uuid_update',
              postID: postID,
              tierUUID: tierUUID,
              nonce: tierNonce,
          },
          success: function(data) {
            //console.log('tier_button_'+postID);
            var button = $('#tier_button_'+postID);
            button.css('background-color', 'green');
            button.css('color', 'white');
          },
          error: function(data) {
            //console.log('tier_button_'+postID);
            var button = $('#tier_button_'+postID);
            button.css('background-color', 'red');
            button.css('color', 'white');
          },
        });
  });

  $('#suborg-search').on('keyup', function() {
      var searchTerm = $(this).val();

      if (searchTerm.length < 3) {
          $('#suborg-results').empty();
          return;
      }

      $.ajax({
          url: ajax_object.ajax_url,
          method: 'POST',
          data: {
              action: 'suborg_search',
              term: searchTerm,
              nonce: $('#suborg_nonce_field').val(),
          },
          success: function(data) {
              var resultsContainer = $('#suborg-results');
              resultsContainer.empty();

              if (data.data.length > 0) {
                  $.each(data.data, function(index, item) {
                      resultsContainer.append('<div class="result-item" data-id="' + item.id + '">' + item.name + '</div>');
                  });
              } else {
                  resultsContainer.append('<div class="no-results">No results found</div>');
              }
              resultsContainer.show();
          }
      });
  });

  $('#suborg-results').on('click', '.result-item', function() {
      var selectedId = $(this).data('id');
      $('#suborg-search').val($(this).text());
      $('#suborg-search-id').val(selectedId);
      $('#suborg-results').empty();
  });
});