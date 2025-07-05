$(document).ready(function () {
    const stagesForm = document.getElementById('opbcrm-stages-form');
    if (!stagesForm) return;

    // Initialize SortableJS on all stage lists
    ['initial-stage-list', 'additional-stages-list'].forEach(listId => {
        const listEl = document.getElementById(listId);
        if (listEl) {
            new Sortable(listEl, {
                group: 'shared-stages',
                animation: 150,
                ghostClass: 'blue-background-class',
                onEnd: updateFunnelPreview
            });
        }
    });

    // Handle 'Add Stage' button clicks
    stagesForm.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-stage-btn')) {
            const group = e.target.dataset.group;
            const stageList = document.getElementById(`${group}-stage-list`);
            const newId = 'new_stage_' + Date.now();
            const newStageHTML = createStageItemHTML(newId, 'New Stage', '#cccccc', true);
            stageList.insertAdjacentHTML('beforeend', newStageHTML);
            updateFunnelPreview();
        }

        // Handle 'Delete' button
        if (e.target.classList.contains('delete-stage-btn')) {
            e.target.closest('.stage-item').remove();
            updateFunnelPreview();
        }

        // Handle 'Edit' button
        if (e.target.classList.contains('edit-stage-btn')) {
            toggleEditView(e.target.closest('.stage-item'), true);
        }

        // Handle 'Save' button
        if (e.target.classList.contains('save-stage-btn')) {
            toggleEditView(e.target.closest('.stage-item'), false);
        }
    });

    // --- Toast feedback ---
    function showToast(msg, color) {
        var $toast = $('#crm-toast');
        $toast.text(msg).css('background', color || 'rgba(30,30,30,0.97)').fadeIn(180);
        setTimeout(function(){ $toast.fadeOut(350); }, 1800);
    }

    // --- Save Stages AJAX (override alert) ---
    $('#opbcrm-stages-form').off('submit').on('submit', function (e) {
        e.preventDefault();
        var spinner = $(this).find('.spinner');
        spinner.addClass('is-active');
        var stagesData = { initial: [], additional: [], success: [], failed: [] };
        $('.stage-list').each(function(){
            var group = $(this).data('group');
            $(this).find('.stage-item').each(function(){
                stagesData[group].push({
                    id: $(this).data('id'),
                    label: $(this).find('.stage-label-input').val(),
                    color: $(this).find('.stage-color-input').val()
                });
            });
        });
        var formData = new FormData(this);
        formData.set('stages', JSON.stringify(stagesData));
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                spinner.removeClass('is-active');
                if (data.success) {
                    showToast('Stages saved successfully!', 'rgba(40,180,80,0.97)');
                } else {
                    showToast('Error: ' + (data.data.message || 'An unknown error occurred.'), 'rgba(200,40,40,0.97)');
                }
            },
            error: function() {
                spinner.removeClass('is-active');
                showToast('An unexpected network error occurred.', 'rgba(200,40,40,0.97)');
            }
        });
    });

    function toggleEditView(stageItem, isEditing) {
        const label = stageItem.querySelector('.stage-label');
        const labelInput = stageItem.querySelector('.stage-label-input');
        const colorInput = stageItem.querySelector('.stage-color-input');
        const editBtn = stageItem.querySelector('.edit-stage-btn');
        const saveBtn = stageItem.querySelector('.save-stage-btn');

        if (isEditing) {
            label.style.display = 'none';
            labelInput.style.display = 'block';
            colorInput.style.display = 'block';
            editBtn.style.display = 'none';
            saveBtn.style.display = 'block';
            labelInput.focus();
        } else {
            label.textContent = labelInput.value;
            stageItem.style.borderLeftColor = colorInput.value;
            
            label.style.display = 'block';
            labelInput.style.display = 'none';
            colorInput.style.display = 'none';
            editBtn.style.display = 'block';
            saveBtn.style.display = 'none';
            updateFunnelPreview();
        }
    }

    function createStageItemHTML(id, label, color, isEditable = true) {
        const actionsHTML = isEditable ? `
            <button type="button" class="button button-small edit-stage-btn">Edit</button>
            <button type="button" class="button button-small save-stage-btn" style="display:none;">Save</button>
            <button type="button" class="button button-link-delete delete-stage-btn">Delete</button>
        ` : '';

        return `
            <div class="stage-item" data-id="${id}" style="border-left-color: ${color}">
                <div class="stage-item-content">
                    <span class="stage-label">${label}</span>
                    <input type="text" class="stage-label-input" value="${label}" style="display:none;">
                    <input type="color" class="stage-color-input" value="${color}" style="display:none;">
                </div>
                <div class="stage-item-actions">${actionsHTML}</div>
            </div>`;
    }

    // --- Colorful funnel preview segments ---
    function updateFunnelPreview() {
        var funnelPreview = $('#funnel-preview');
        funnelPreview.empty();
        var initialStage = $('#initial-stage-list .stage-item');
        var additionalStages = $('#additional-stages-list .stage-item');
        if (initialStage.length) {
            var color = initialStage.find('.stage-color-input').val();
            var seg = $('<div class="funnel-segment"></div>').css({background: color, borderRadius:'8px', minWidth:'32px', minHeight:'28px', marginRight:'7px', boxShadow:'0 1px 6px 0 rgba(31,38,135,0.09)'});
            funnelPreview.append(seg);
        }
        additionalStages.each(function(){
            var color = $(this).find('.stage-color-input').val();
            var seg = $('<div class="funnel-segment"></div>').css({background: color, borderRadius:'8px', minWidth:'32px', minHeight:'28px', marginRight:'7px', boxShadow:'0 1px 6px 0 rgba(31,38,135,0.09)'});
            funnelPreview.append(seg);
        });
    }

    // --- Compact, glassy UI for stage items ---
    $(document).on('mouseenter', '.stage-item', function(){
        $(this).css('box-shadow','0 2px 12px 0 rgba(31,38,135,0.13)');
    }).on('mouseleave', '.stage-item', function(){
        $(this).css('box-shadow','none');
    });

    // --- Initial call ---
    updateFunnelPreview();

    // --- Also update funnel preview on any stage change ---
    $(document).on('input change', '.stage-label-input, .stage-color-input', updateFunnelPreview);
    $(document).on('DOMNodeInserted DOMNodeRemoved', '.stage-list', updateFunnelPreview);
}); 