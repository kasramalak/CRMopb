// OPBCRM Proposal Generator functionality
jQuery(document).ready(function($) {

    const modal = $('#proposal-modal');
    const openBtn = $('#generate-proposal-btn');
    const closeBtn = $('.opbcrm-modal-close');
    const searchInput = $('#property-search');
    const searchResults = $('#property-search-results');
    const selectedPropertyId = $('#selected-property-id');
    const generateBtn = $('#do-generate-proposal');

    // Open modal
    openBtn.on('click', function() {
        modal.show();
    });

    // Close modal
    closeBtn.on('click', function() {
        modal.hide();
    });

    // Close modal if clicking outside of it
    $(window).on('click', function(e) {
        if ($(e.target).is(modal)) {
            modal.hide();
        }
    });

    // Property Search AJAX
    let debounceTimer;
    searchInput.on('keyup', function() {
        clearTimeout(debounceTimer);
        const searchTerm = $(this).val();

        if (searchTerm.length < 3) {
            searchResults.empty();
            return;
        }

        debounceTimer = setTimeout(function() {
            $.ajax({
                url: opbCrmProposalAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'search_opbez_properties',
                    nonce: opbCrmProposalAjax.nonce,
                    search: searchTerm,
                },
                success: function(response) {
                    searchResults.empty();
                    if (response.success && response.data.length > 0) {
                        const list = $('<ul></ul>');
                        response.data.forEach(function(item) {
                            list.append('<li data-id="' + item.id + '">' + item.title + '</li>');
                        });
                        searchResults.html(list);
                    } else {
                        searchResults.html('<p>No properties found.</p>');
                    }
                }
            });
        }, 500); // 500ms debounce
    });
    
    // Handle clicking on a search result
    searchResults.on('click', 'li', function() {
        const propertyId = $(this).data('id');
        const propertyTitle = $(this).text();
        selectedPropertyId.val(propertyId);
        searchInput.val(propertyTitle);
        searchResults.empty();
    });

    // Generate Proposal AJAX
    generateBtn.on('click', function() {
        const propertyId = selectedPropertyId.val();
        const templateId = $('#proposal-template').val();
        const leadId = opbCrmProposalAjax.leadId;

        if (!propertyId || !templateId) {
            alert('Please select a property and a template.');
            return;
        }
        
        $(this).prop('disabled', true).text('Generating...');

        $.ajax({
            url: opbCrmProposalAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'generate_crm_proposal',
                nonce: opbCrmProposalAjax.nonce,
                lead_id: leadId,
                property_id: propertyId,
                template_id: templateId,
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    modal.hide();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            complete: function() {
                 generateBtn.prop('disabled', false).text('Generate');
            }
        });
    });
}); 