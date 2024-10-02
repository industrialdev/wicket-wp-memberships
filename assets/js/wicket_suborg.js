jQuery(document).ready(function($) {
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