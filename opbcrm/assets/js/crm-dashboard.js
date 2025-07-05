jQuery(document).ready(function ($) {
    
    // --- Modal Handling ---
    const modal = $('#add-new-lead-modal');
    const addNewLeadBtn = $('#add-new-lead-btn');
    const closeModalBtn = $('.opbcrm-modal-close');

    addNewLeadBtn.on('click', function() {
        modal.show();
        // --- Populate Agent Dropdown ---
        const agentSelect = $('#agent_id');
        agentSelect.html('<option value="">Loading...</option>');
        $.post(opbcrm_dashboard_ajax.ajax_url, {action: 'opbcrm_get_crm_agents'}, function(response) {
            if (response.success && response.data && response.data.agents) {
                let options = '<option value="">Select Agent</option>';
                response.data.agents.forEach(function(agent) {
                    options += `<option value="${agent.id}">${agent.name}</option>`;
                });
                agentSelect.html(options);
            } else {
                agentSelect.html('<option value="">No agents found</option>');
            }
        });
        // --- Reset Substage ---
        $('#lead-substage-group').hide();
        $('#lead-sub-stage').html('');
    });

    closeModalBtn.on('click', function() {
        modal.hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).is(modal)) {
            modal.hide();
        }
    });

    // --- Add New Lead AJAX Form Submission ---
    $('#add-new-lead-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const spinner = form.find('.spinner');
        const submitBtn = form.find('button[type="submit"]');

        spinner.addClass('is-active');
        submitBtn.prop('disabled', true);

        const formData = form.serialize() + '&action=opbcrm_add_new_lead';

        $.post(opbcrm_dashboard_ajax.ajax_url, formData, function(response) {
            spinner.removeClass('is-active');
            submitBtn.prop('disabled', false);

            if (response.success) {
                alert('Lead added successfully!');
                modal.hide();
                form[0].reset();
                
                // Add lead card to the board dynamically
                const newLead = response.data.lead_html;
                const stageId = response.data.stage_id;
                $(`.pipeline-leads-list[data-stage-id="${stageId}"]`).prepend(newLead);
                
                // Update stage count
                const countSpan = $(`.pipeline-stage[data-stage-id="${stageId}"]`).find('.pipeline-stage-count');
                countSpan.text(parseInt(countSpan.text()) + 1);

            } else {
                alert('Error: ' + response.data.message);
            }
        }).fail(function() {
            spinner.removeClass('is-active');
            submitBtn.prop('disabled', false);
            alert('An unexpected error occurred. Please try again.');
        });
    });

    // --- Kanban Drag and Drop ---
    if (opbcrm_field_permissions.edit_leads) {
        $('.pipeline-leads-list').sortable({
            connectWith: '.pipeline-leads-list',
            placeholder: 'lead-card-placeholder',
            start: function(event, ui) {
                // Add loading overlay to the card being dragged
                ui.item.append('<div class="kanban-card-overlay"><div class="kanban-spinner"></div></div>');
            },
            stop: function(event, ui) {
                // Remove overlay if still present
                ui.item.find('.kanban-card-overlay').remove();
            },
            update: function(event, ui) {
                if (this === ui.item.parent()[0]) {
                    const leadId = ui.item.data('lead-id');
                    const newStageId = $(this).data('stage-id');
                    const originalStageId = ui.sender ? ui.sender.data('stage-id') : newStageId;
                    // Show overlay
                    ui.item.find('.kanban-card-overlay').show();
                    $.post(opbcrm_dashboard_ajax.ajax_url, {
                        action: 'opbcrm_update_lead_stage',
                        nonce: opbcrm_dashboard_ajax.nonce,
                        lead_id: leadId,
                        new_stage_id: newStageId
                    }, function(response) {
                        ui.item.find('.kanban-card-overlay').remove();
                        if (!response.success) {
                            showToast('Could not update lead stage: ' + response.data.message, false);
                            // Revert the drag-and-drop
                            $(ui.sender).sortable('cancel');
                        } else {
                            // Update counts
                            const newCountSpan = $(`.pipeline-stage[data-stage-id="${newStageId}"]`).find('.pipeline-stage-count');
                            newCountSpan.text(parseInt(newCountSpan.text()) + 1);
                            if(ui.sender) {
                                const oldCountSpan = ui.sender.closest('.pipeline-stage').find('.pipeline-stage-count');
                                oldCountSpan.text(parseInt(oldCountSpan.text()) - 1);
                            }
                            // Animate card
                            ui.item.addClass('kanban-card-success');
                            setTimeout(function(){ ui.item.removeClass('kanban-card-success'); }, 700);
                            showToast('Lead moved successfully!', true);
                        }
                    });
                }
            }
        }).disableSelection();
    }

    // --- Dynamic Substage Handling ---
    $('#lead-stage').on('change', function() {
        const stageId = $(this).val();
        if (!stageId) {
            $('#lead-substage-group').hide();
            $('#lead-sub-stage').html('');
            return;
        }
        // Fetch substages for this stage
        $.post(opbcrm_dashboard_ajax.ajax_url, {
            action: 'opbcrm_get_substages',
            stage_id: stageId
        }, function(response) {
            if (response.success && response.data && response.data.substages && response.data.substages.length > 0) {
                let options = '<option value="">Select Sub-Stage</option>';
                response.data.substages.forEach(function(sub) {
                    options += `<option value="${sub.id}">${sub.label}</option>`;
                });
                $('#lead-sub-stage').html(options);
                $('#lead-substage-group').show();
                if (response.data.required) {
                    $('#substage-required').show();
                    $('#lead-sub-stage').attr('required', true);
                } else {
                    $('#substage-required').hide();
                    $('#lead-sub-stage').removeAttr('required');
                }
            } else {
                $('#lead-substage-group').hide();
                $('#lead-sub-stage').html('');
            }
        });
    });

    // --- Dynamic Property Picker (Proposal) ---
    $('.lead-tab-list li[data-tab="proposal"]').on('click', function() {
        const propertySelect = $('#lead-proposal');
        propertySelect.html('<option value="">Loading...</option>');
        $.post(opbcrm_dashboard_ajax.ajax_url, {action: 'opbcrm_get_properties'}, function(response) {
            if (response.success && response.data && response.data.properties) {
                let options = '<option value="">Select Property</option>';
                response.data.properties.forEach(function(prop) {
                    options += `<option value="${prop.id}">${prop.title}</option>`;
                });
                propertySelect.html(options);
            } else {
                propertySelect.html('<option value="">No properties found</option>');
            }
        });
    });

    // --- Multi-phone add/remove ---
    $('#add-phone-btn').on('click', function() {
        const phoneRow = `<div class="form-row phone-row">
            <div class="floating-label-group form-col">
                <select name="country_code[]" class="country-code-select" required>
                    <option value="" disabled selected hidden></option>
                    <option value="+971">🇦🇪 +971</option>
                    <option value="+98">🇮🇷 +98</option>
                    <option value="+1">🇺🇸 +1</option>
                    <option value="+44">🇬🇧 +44</option>
                    <option value="+49">🇩🇪 +49</option>
                </select>
                <label>Code</label>
            </div>
            <div class="floating-label-group form-col">
                <input type="tel" name="lead_phone[]" required placeholder=" " />
                <label>Phone <span style='color:#83A2DB'>*</span></label>
            </div>
            <button type="button" class="remove-phone-btn crm-btn" style="margin-left:8px;">&times;</button>
        </div>`;
        $('#phone-fields').append(phoneRow);
    });
    $(document).on('click', '.remove-phone-btn', function() {
        $(this).closest('.phone-row').remove();
    });

    // --- Dynamic dropdowns with 'Add new' (AJAX integration) ---
    $('#lead_source').on('change', function() {
        if ($(this).val() === 'AddNew') {
            $('#new-source-group').show();
            $('#new_source').prop('required', true);
        } else {
            $('#new-source-group').hide();
            $('#new_source').prop('required', false);
        }
    });
    $('#new_source').on('blur', function() {
        var val = $(this).val().trim();
        if (val) {
            $.post(opbcrm_dashboard_ajax.ajax_url, {
                action: 'opbcrm_add_lead_source',
                source: val
            }, function(response) {
                if (response.success && response.sources) {
                    var opts = '';
                    response.sources.forEach(function(src) {
                        opts += `<option value="${src}">${src}</option>`;
                    });
                    opts += '<option value="AddNew">+ Add new source...</option>';
                    $('#lead_source').html(opts).val(val);
                    $('#new-source-group').hide();
                }
            });
        }
    });
    $('#lead_property_type').on('change', function() {
        if ($(this).val() === 'AddNew') {
            $('#new-type-group').show();
            $('#new_property_type').prop('required', true);
        } else {
            $('#new-type-group').hide();
            $('#new_property_type').prop('required', false);
        }
    });
    $('#new_property_type').on('blur', function() {
        var val = $(this).val().trim();
        if (val) {
            $.post(opbcrm_dashboard_ajax.ajax_url, {
                action: 'opbcrm_add_property_type',
                property_type: val
            }, function(response) {
                if (response.success && response.types) {
                    var opts = '';
                    response.types.forEach(function(type) {
                        opts += `<option value="${type}">${type}</option>`;
                    });
                    opts += '<option value="AddNew">+ Add new type...</option>';
                    $('#lead_property_type').html(opts).val(val);
                    $('#new-type-group').hide();
                }
            });
        }
    });

    // --- Submit multi-phone as JSON array and all new fields ---
    $('#lead-form').on('submit', function(e) {
        // ... existing code ...
        var phones = [];
        $('#phone-fields .phone-row').each(function() {
            var code = $(this).find('select.country-code-select').val();
            var phone = $(this).find('input[type="tel"]').val();
            if (code && phone) phones.push(code + phone);
        });
        $('<input type="hidden" name="lead_phone_json" />').val(JSON.stringify(phones)).appendTo(this);
        // Ensure all new fields are included
        // (Assume fields: developer, project, bedrooms, property_type, location, agent_comment)
        // ... existing code ...
    });

    // --- Inline editable Agent Comment (AJAX integration) ---
    $(document).on('click', '.save-agent-comment', function() {
        const td = $(this).closest('td');
        const newComment = td.find('.agent-comment-edit').val();
        const leadId = td.closest('tr').data('lead-id');
        $.post(opbcrm_dashboard_ajax.ajax_url, {
            action: 'opbcrm_update_agent_comment',
            lead_id: leadId,
            comment: newComment
        }, function(response) {
            if (response.success) {
                td.html(`<span class='agent-comment-text'>${newComment}</span> <button class='edit-agent-comment crm-btn'>Edit</button>`);
            } else {
                alert('Error updating comment');
            }
        });
    });

    // --- Permission-aware UI ---
    function applyFieldPermissions() {
        // Hide or disable fields/actions based on opbcrm_field_permissions
        $.each(opbcrm_field_permissions, function(field, perms) {
            if (perms.view === false) {
                $(`.field-${field}`).hide();
            }
            if (perms.edit === false) {
                $(`.field-${field} input, .field-${field} select, .field-${field} textarea`).prop('disabled', true);
            }
        });
        // Hide Add Lead button if no permission
        if (!opbcrm_field_permissions.add_leads) {
            $('#add-lead-btn').hide();
        }
    }
    if (typeof opbcrm_field_permissions !== 'undefined') {
        applyFieldPermissions();
    }

    // --- Auto-fill date fields ---
    if ($('#create_date').length && !$('#create_date').val()) {
        const now = new Date().toLocaleString();
        $('#create_date').val(now);
    }
    if ($('#modify_date').length && !$('#modify_date').val()) {
        const now = new Date().toLocaleString();
        $('#modify_date').val(now);
    }

    // --- Admin Users Modal/Table Logic (Permission-Aware, OPBCRM) ---
    if (typeof opbcrm_user_field_permissions !== 'undefined') {
        function applyUserFieldPermissions() {
            if (!opbcrm_user_field_permissions.add_user) {
                $('#opbcrm-add-user-btn').hide();
            }
            if (!opbcrm_user_field_permissions.username.edit) $('#opbcrm-user-username').closest('.floating-label-group').hide();
            if (!opbcrm_user_field_permissions.email.edit) $('#opbcrm-user-email').closest('.floating-label-group').hide();
            if (!opbcrm_user_field_permissions.role.edit) $('#opbcrm-user-role').closest('.floating-label-group').hide();
            if (!(opbcrm_user_field_permissions.add_user || opbcrm_user_field_permissions.edit_user)) {
                $('#opbcrm-user-form button[type="submit"]').hide();
            }
        }
        applyUserFieldPermissions();
    }
    // Open Add User modal
    $(document).on('click', '#opbcrm-add-user-btn', function() {
        $('#opbcrm-user-form')[0].reset();
        $('#opbcrm-user-id').val('');
        $('#opbcrm-user-modal').show();
    });
    // Open Edit User modal
    $(document).on('click', '.edit-user-btn', function() {
        var row = $(this).closest('tr');
        $('#opbcrm-user-id').val(row.data('user-id'));
        $('#opbcrm-user-username').val(row.find('td:eq(0)').text());
        $('#opbcrm-user-email').val(row.find('td:eq(1)').text());
        $('#opbcrm-user-role').val(row.find('td:eq(2)').text().toLowerCase());
        $('#opbcrm-user-password').val('');
        $('#opbcrm-user-modal').show();
    });
    // Close modal
    $(document).on('click', '#close-user-modal, #cancel-user-btn', function() {
        $('#opbcrm-user-modal').hide();
    });
    // Submit form via AJAX
    $(document).on('submit', '#opbcrm-user-form', function(e) {
        e.preventDefault();
        var isEdit = $('#opbcrm-user-id').val() != '';
        var data = $(this).serialize()+'&action=opbcrm_save_user&_ajax_nonce='+opbcrm_save_user_nonce;
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true);
        $.post(ajaxurl, data, function(response) {
            btn.prop('disabled', false);
            if (response.success && response.id) {
                showToast('User saved successfully!', true);
                $('#opbcrm-user-modal').hide();
                location.reload();
            } else {
                showToast(response.data && response.data.message ? response.data.message : 'Error saving user', false);
            }
        }).fail(function() {
            btn.prop('disabled', false);
            showToast('An unexpected error occurred.', false);
        });
    });
    // Delete user
    $(document).on('click', '.delete-user-btn', function() {
        if (!confirm('Are you sure you want to delete this user?')) return;
        var userId = $(this).data('user-id');
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'opbcrm_delete_user',
            user_id: userId,
            _ajax_nonce: opbcrm_delete_user_nonce
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                showToast('User deleted!', true);
                location.reload();
            } else {
                showToast(response.data && response.data.message ? response.data.message : 'Error deleting user', false);
            }
        }).fail(function() {
            btn.prop('disabled', false);
            showToast('An unexpected error occurred.', false);
        });
    });

    // --- Inline editing for agent comment on Kanban cards ---
    $(document).on('click', '.kanban-edit-comment-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        var commentRow = card.find('.kanban-agent-comment');
        var editRow = card.find('.kanban-edit-comment-row');
        var text = commentRow.find('.kanban-agent-comment-text').text();
        editRow.find('.kanban-edit-comment-textarea').val(text === 'No comment' ? '' : text);
        commentRow.hide();
        editRow.show();
    });
    $(document).on('click', '.kanban-cancel-comment-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        card.find('.kanban-edit-comment-row').hide();
        card.find('.kanban-agent-comment').show();
    });
    $(document).on('click', '.kanban-save-comment-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        var leadId = card.data('lead-id');
        var newComment = card.find('.kanban-edit-comment-textarea').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(opbcrm_dashboard_ajax.ajax_url, {
            action: 'opbcrm_update_agent_comment',
            lead_id: leadId,
            comment: newComment
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                card.find('.kanban-agent-comment-text').text(newComment ? newComment : 'No comment');
                card.find('.kanban-edit-comment-row').hide();
                card.find('.kanban-agent-comment').show();
                showToast('Comment updated!', true);
            } else {
                showToast('Error updating comment', false);
            }
        });
    });

    // --- Inline editing for Project on Kanban cards ---
    $(document).on('click', '.kanban-edit-project-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        card.find('.kanban-project-view').hide();
        $(this).hide();
        card.find('.kanban-edit-project-row').show();
    });
    $(document).on('click', '.kanban-cancel-project-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        card.find('.kanban-edit-project-row').hide();
        card.find('.kanban-project-view').show();
        card.find('.kanban-edit-project-btn').show();
    });
    $(document).on('click', '.kanban-save-project-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        var leadId = card.data('lead-id');
        var newProject = card.find('.kanban-edit-project-input').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(opbcrm_dashboard_ajax.ajax_url, {
            action: 'opbcrm_update_lead_field',
            lead_id: leadId,
            field: 'lead_project',
            value: newProject
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                card.find('.kanban-project-view').text(newProject);
                card.find('.kanban-edit-project-row').hide();
                card.find('.kanban-project-view').show();
                card.find('.kanban-edit-project-btn').show();
                showToast('Project updated!', true);
            } else {
                showToast('Error updating project', false);
            }
        });
    });

    // --- Inline editing for Source on Kanban cards ---
    $(document).on('click', '.kanban-edit-source-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        card.find('.kanban-source-view').hide();
        $(this).hide();
        card.find('.kanban-edit-source-row').show();
    });
    $(document).on('change', '.kanban-edit-source-select', function() {
        var sel = $(this);
        var card = sel.closest('.crm-kanban-card');
        if (sel.val() === 'AddNew') {
            card.find('.kanban-new-source-input').show().focus();
        } else {
            card.find('.kanban-new-source-input').hide();
        }
    });
    $(document).on('click', '.kanban-cancel-source-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        card.find('.kanban-edit-source-row').hide();
        card.find('.kanban-source-view').show();
        card.find('.kanban-edit-source-btn').show();
    });
    $(document).on('click', '.kanban-save-source-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        var leadId = card.data('lead-id');
        var sel = card.find('.kanban-edit-source-select');
        var newSource = sel.val();
        var newSourceInput = card.find('.kanban-new-source-input');
        var btn = $(this);
        btn.prop('disabled', true);
        function finishUpdate(sourceVal) {
            $.post(opbcrm_dashboard_ajax.ajax_url, {
                action: 'opbcrm_update_lead_field',
                lead_id: leadId,
                field: 'lead_source',
                value: sourceVal
            }, function(response) {
                btn.prop('disabled', false);
                if (response.success) {
                    card.find('.kanban-source-view').text(sourceVal);
                    card.find('.kanban-edit-source-row').hide();
                    card.find('.kanban-source-view').show();
                    card.find('.kanban-edit-source-btn').show();
                    showToast('Source updated!', true);
                } else {
                    showToast('Error updating source', false);
                }
            });
        }
        if (newSource === 'AddNew') {
            var val = newSourceInput.val().trim();
            if (!val) { showToast('Enter new source', false); btn.prop('disabled', false); return; }
            $.post(opbcrm_dashboard_ajax.ajax_url, {
                action: 'opbcrm_add_lead_source',
                source: val
            }, function(response) {
                if (response.success) {
                    finishUpdate(val);
                } else {
                    showToast('Error adding source', false);
                    btn.prop('disabled', false);
                }
            });
        } else {
            finishUpdate(newSource);
        }
    });

    // --- Inline editing for Campaign on Kanban cards ---
    $(document).on('click', '.kanban-edit-campaign-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        card.find('.kanban-campaign-view').hide();
        $(this).hide();
        card.find('.kanban-edit-campaign-row').show();
    });
    $(document).on('click', '.kanban-cancel-campaign-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        card.find('.kanban-edit-campaign-row').hide();
        card.find('.kanban-campaign-view').show();
        card.find('.kanban-edit-campaign-btn').show();
    });
    $(document).on('click', '.kanban-save-campaign-btn', function() {
        var card = $(this).closest('.crm-kanban-card');
        var leadId = card.data('lead-id');
        var newCampaign = card.find('.kanban-edit-campaign-input').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(opbcrm_dashboard_ajax.ajax_url, {
            action: 'opbcrm_update_lead_field',
            lead_id: leadId,
            field: 'lead_campaign',
            value: newCampaign
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                card.find('.kanban-campaign-view').text(newCampaign ? newCampaign : '—');
                card.find('.kanban-edit-campaign-row').hide();
                card.find('.kanban-campaign-view').show();
                card.find('.kanban-edit-campaign-btn').show();
                showToast('Campaign updated!', true);
            } else {
                showToast('Error updating campaign', false);
            }
        });
    });

    // --- Inline editing for Campaign in Leads Table ---
    $(document).on('click', '.table-edit-campaign-btn', function() {
        var td = $(this).closest('td');
        td.find('.table-campaign-view').hide();
        $(this).hide();
        td.find('.table-edit-campaign-row').show();
    });
    $(document).on('click', '.table-cancel-campaign-btn', function() {
        var td = $(this).closest('td');
        td.find('.table-edit-campaign-row').hide();
        td.find('.table-campaign-view').show();
        td.find('.table-edit-campaign-btn').show();
    });
    $(document).on('click', '.table-save-campaign-btn', function() {
        var td = $(this).closest('td');
        var tr = td.closest('tr');
        var leadId = tr.data('lead-id');
        var newCampaign = td.find('.table-edit-campaign-input').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(opbcrm_dashboard_ajax.ajax_url, {
            action: 'opbcrm_update_lead_field',
            lead_id: leadId,
            field: 'lead_campaign',
            value: newCampaign
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                td.find('.table-campaign-view').text(newCampaign ? newCampaign : '—');
                td.find('.table-edit-campaign-row').hide();
                td.find('.table-campaign-view').show();
                td.find('.table-edit-campaign-btn').show();
                showToast('Campaign updated!', true);
            } else {
                showToast('Error updating campaign', false);
            }
        });
    });

    // --- Inline editing for Users table ---
    // Name
    $(document).on('click', '.user-edit-name-btn', function() {
        var row = $(this).closest('tr');
        row.find('.user-name-view').hide();
        $(this).hide();
        row.find('.user-edit-name-row').show();
    });
    $(document).on('click', '.user-cancel-name-btn', function() {
        var row = $(this).closest('tr');
        row.find('.user-edit-name-row').hide();
        row.find('.user-name-view').show();
        row.find('.user-edit-name-btn').show();
    });
    $(document).on('click', '.user-save-name-btn', function() {
        var row = $(this).closest('tr');
        var userId = row.data('user-id');
        var newVal = row.find('.user-edit-name-input').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'opbcrm_inline_update_user_field',
            user_id: userId,
            field: 'display_name',
            value: newVal
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                row.find('.user-name-view').text(newVal);
                row.find('.user-edit-name-row').hide();
                row.find('.user-name-view').show();
                row.find('.user-edit-name-btn').show();
                showToast('Name updated!', true);
            } else {
                showToast('Error updating name', false);
            }
        });
    });
    // Email
    $(document).on('click', '.user-edit-email-btn', function() {
        var row = $(this).closest('tr');
        row.find('.user-email-view').hide();
        $(this).hide();
        row.find('.user-edit-email-row').show();
    });
    $(document).on('click', '.user-cancel-email-btn', function() {
        var row = $(this).closest('tr');
        row.find('.user-edit-email-row').hide();
        row.find('.user-email-view').show();
        row.find('.user-edit-email-btn').show();
    });
    $(document).on('click', '.user-save-email-btn', function() {
        var row = $(this).closest('tr');
        var userId = row.data('user-id');
        var newVal = row.find('.user-edit-email-input').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'opbcrm_inline_update_user_field',
            user_id: userId,
            field: 'user_email',
            value: newVal
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                row.find('.user-email-view').html('<i class="fas fa-envelope" style="margin-right:4px;color:#007bff;"></i>' + newVal);
                row.find('.user-edit-email-row').hide();
                row.find('.user-email-view').show();
                row.find('.user-edit-email-btn').show();
                showToast('Email updated!', true);
            } else {
                showToast('Error updating email', false);
            }
        });
    });
    // Mobile
    $(document).on('click', '.user-edit-mobile-btn', function() {
        var row = $(this).closest('tr');
        row.find('.user-mobile-view').hide();
        $(this).hide();
        row.find('.user-edit-mobile-row').show();
    });
    $(document).on('click', '.user-cancel-mobile-btn', function() {
        var row = $(this).closest('tr');
        row.find('.user-edit-mobile-row').hide();
        row.find('.user-mobile-view').show();
        row.find('.user-edit-mobile-btn').show();
    });
    $(document).on('click', '.user-save-mobile-btn', function() {
        var row = $(this).closest('tr');
        var userId = row.data('user-id');
        var newVal = row.find('.user-edit-mobile-input').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'opbcrm_inline_update_user_field',
            user_id: userId,
            field: 'mobile',
            value: newVal
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                row.find('.user-mobile-view').html('<i class="fas fa-mobile-alt" style="margin-right:4px;color:#28a745;"></i>' + newVal);
                row.find('.user-edit-mobile-row').hide();
                row.find('.user-mobile-view').show();
                row.find('.user-edit-mobile-btn').show();
                showToast('Mobile updated!', true);
            } else {
                showToast('Error updating mobile', false);
            }
        });
    });
    // Role
    $(document).on('click', '.user-edit-role-btn', function() {
        var row = $(this).closest('tr');
        row.find('.user-role-view').hide();
        $(this).hide();
        row.find('.user-edit-role-row').show();
    });
    $(document).on('click', '.user-cancel-role-btn', function() {
        var row = $(this).closest('tr');
        row.find('.user-edit-role-row').hide();
        row.find('.user-role-view').show();
        row.find('.user-edit-role-btn').show();
    });
    $(document).on('click', '.user-save-role-btn', function() {
        var row = $(this).closest('tr');
        var userId = row.data('user-id');
        var newVal = row.find('.user-edit-role-select').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'opbcrm_inline_update_user_field',
            user_id: userId,
            field: 'role',
            value: newVal
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                row.find('.user-role-view').html(response.role_badge_html);
                row.find('.user-edit-role-row').hide();
                row.find('.user-role-view').show();
                row.find('.user-edit-role-btn').show();
                showToast('Role updated!', true);
            } else {
                showToast('Error updating role', false);
            }
        });
    });

    // --- Delete custom field ---
    $(document).on('click', '.crm-delete-field-btn', function(e) {
        e.preventDefault();
        var row = $(this).closest('tr');
        var fieldId = row.data('field-id');
        if (!fieldId) return;
        if (!confirm('Are you sure you want to delete this custom field?')) return;
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'opbcrm_delete_custom_field',
            field_id: fieldId
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                row.fadeOut(250, function(){ $(this).remove(); });
                showToast('Custom field deleted!', true);
            } else {
                showToast(response.data && response.data.message ? response.data.message : 'Error deleting field', false);
            }
        });
    });

    // --- Inline edit custom field label ---
    $(document).on('click', '.crm-edit-field-btn', function() {
        var row = $(this).closest('tr');
        row.find('.cf-label-view,.crm-edit-field-btn').hide();
        row.find('.cf-label-edit-row').show();
    });
    $(document).on('click', '.cf-cancel-label-btn', function() {
        var row = $(this).closest('tr');
        row.find('.cf-label-edit-row').hide();
        row.find('.cf-label-view,.crm-edit-field-btn').show();
    });
    $(document).on('click', '.cf-save-label-btn', function() {
        var row = $(this).closest('tr');
        var fieldId = row.data('field-id');
        var newVal = row.find('.cf-edit-label-input').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'opbcrm_inline_update_custom_field',
            field_id: fieldId,
            field: 'field_label',
            value: newVal
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                row.find('.cf-label-view').text(newVal);
                row.find('.cf-label-edit-row').hide();
                row.find('.cf-label-view,.crm-edit-field-btn').show();
                showToast('Label updated!', true);
            } else {
                showToast('Error updating label', false);
            }
        });
    });
    // --- Inline edit custom field type ---
    $(document).on('click', '.crm-edit-type-btn', function() {
        var row = $(this).closest('tr');
        row.find('.cf-type-view,.crm-edit-type-btn').hide();
        row.find('.cf-type-edit-row').show();
    });
    $(document).on('click', '.cf-cancel-type-btn', function() {
        var row = $(this).closest('tr');
        row.find('.cf-type-edit-row').hide();
        row.find('.cf-type-view,.crm-edit-type-btn').show();
    });
    $(document).on('click', '.cf-save-type-btn', function() {
        var row = $(this).closest('tr');
        var fieldId = row.data('field-id');
        var newVal = row.find('.cf-edit-type-select').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'opbcrm_inline_update_custom_field',
            field_id: fieldId,
            field: 'field_type',
            value: newVal
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                // If type changed to/from select, reload for options UI
                location.reload();
            } else {
                showToast('Error updating type', false);
            }
        });
    });
    // --- Inline edit custom field options ---
    $(document).on('click', '.crm-edit-options-btn', function() {
        var row = $(this).closest('tr');
        row.find('.cf-options-view,.crm-edit-options-btn').hide();
        row.find('.cf-options-edit-row').show();
    });
    $(document).on('click', '.cf-cancel-options-btn', function() {
        var row = $(this).closest('tr');
        row.find('.cf-options-edit-row').hide();
        row.find('.cf-options-view,.crm-edit-options-btn').show();
    });
    $(document).on('click', '.cf-save-options-btn', function() {
        var row = $(this).closest('tr');
        var fieldId = row.data('field-id');
        var newVal = row.find('.cf-edit-options-input').val();
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'opbcrm_inline_update_custom_field',
            field_id: fieldId,
            field: 'field_options',
            value: newVal
        }, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                row.find('.cf-options-view').text(newVal);
                row.find('.cf-options-edit-row').hide();
                row.find('.cf-options-view,.crm-edit-options-btn').show();
                showToast('Options updated!', true);
            } else {
                showToast('Error updating options', false);
            }
        });
    });

    // --- AJAX Add New Custom Field ---
    $('#crm-add-custom-field-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var label = $form.find('[name="field_label"]').val();
        var type = $form.find('[name="field_type"]').val();
        var options = $form.find('[name="field_options"]').val();
        var nonce = $form.find('input[name="_wpnonce"]').val();
        $btn.prop('disabled', true);
        $.post(ajaxurl, {
            action: 'opbcrm_add_custom_field_ajax',
            field_label: label,
            field_type: type,
            field_options: options,
            _wpnonce: nonce
        }, function(response) {
            $btn.prop('disabled', false);
            if (response.success && response.data && response.data.row_html) {
                $('#crm-custom-fields-tbody').prepend(response.data.row_html);
                $form[0].reset();
                $form.find('#field-options-container').hide();
                showToast('Custom field added!', true);
            } else {
                showToast(response.data && response.data.message ? response.data.message : 'Error adding field', false);
            }
        });
    });

    // --- Custom Fields Table Search/Filter ---
    $('#crm-custom-fields-search').on('input', function() {
        var val = $(this).val().toLowerCase();
        $('#crm-custom-fields-tbody tr').each(function() {
            var row = $(this);
            var text = row.text().toLowerCase();
            if (text.indexOf(val) > -1) {
                row.show();
            } else {
                row.hide();
            }
        });
    });

    // --- Campaign Filter for Leads Table and Kanban ---
    $('#campaign-filter').on('change', function() {
        var selected = $(this).val();
        // Table
        $('#leads-tbody tr').each(function() {
            var campaign = $(this).find('.field-campaign .table-campaign-view').text().trim();
            if (!selected || campaign === selected) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        // Kanban
        $('.crm-kanban-card.lead-card').each(function() {
            var campaign = $(this).find('.kanban-campaign-view').text().trim();
            if (!selected || campaign === selected) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // --- Bulk Campaign Assignment ---
    $(document).on('change', '#leads-bulk-select-all, #leads-table-select-all', function() {
        var checked = $(this).is(':checked');
        $('.lead-row-checkbox').prop('checked', checked);
    });

    $(document).on('change', '.lead-row-checkbox', function() {
        var all = $('.lead-row-checkbox').length;
        var checked = $('.lead-row-checkbox:checked').length;
        $('#leads-bulk-select-all, #leads-table-select-all').prop('checked', all === checked);
    });

    $(document).on('click', '#bulk-assign-campaign-btn', function() {
        var selectedLeads = $('.lead-row-checkbox:checked').closest('tr').map(function() {
            return $(this).data('lead-id');
        }).get();
        var campaign = $('#bulk-campaign-select').val();
        if (!campaign) {
            showToast('error', 'Please select a campaign.');
            return;
        }
        if (!selectedLeads.length) {
            showToast('error', 'Please select at least one lead.');
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true).text('Assigning...');
        $.post(ajaxurl, {
            action: 'opbcrm_bulk_assign_campaign',
            nonce: opbcrm_dashboard_vars.nonce,
            lead_ids: selectedLeads,
            campaign: campaign
        }).done(function(response) {
            if (response.success) {
                showToast('success', 'Campaign assigned to selected leads.');
                // Update UI for affected rows
                $('.lead-row-checkbox:checked').each(function() {
                    var row = $(this).closest('tr');
                    row.find('.field-campaign .table-campaign-view').text(campaign);
                });
                $('.lead-row-checkbox, #leads-bulk-select-all, #leads-table-select-all').prop('checked', false);
            } else {
                showToast('error', response.data && response.data.message ? response.data.message : 'Bulk assignment failed.');
            }
        }).fail(function() {
            showToast('error', 'Request failed.');
        }).always(function() {
            btn.prop('disabled', false).text('Assign Campaign');
        });
    });
}); 