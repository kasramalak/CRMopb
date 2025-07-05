// OPBCRM Pipeline Drag & Drop functionality
document.addEventListener('DOMContentLoaded', function () {
    const leadCards = document.querySelectorAll('.lead-card');
    const stages = document.querySelectorAll('.pipeline-stage');

    let draggedItem = null;

    leadCards.forEach(card => {
        card.addEventListener('dragstart', function() {
            draggedItem = this;
            setTimeout(() => {
                this.style.display = 'none';
            }, 0);
        });

        card.addEventListener('dragend', function() {
            setTimeout(() => {
                draggedItem.style.display = 'block';
                draggedItem = null;
            }, 0);
        });
    });

    stages.forEach(stage => {
        stage.addEventListener('dragover', function(e) {
            e.preventDefault();
        });

        stage.addEventListener('dragenter', function(e) {
            e.preventDefault();
            this.style.backgroundColor = 'rgba(0,0,0,0.1)';
        });

        stage.addEventListener('dragleave', function() {
            this.style.backgroundColor = '#f4f4f4';
        });

        stage.addEventListener('drop', function() {
            if (draggedItem) {
                const leadsContainer = this.querySelector('.pipeline-leads');
                leadsContainer.appendChild(draggedItem);
                this.style.backgroundColor = '#f4f4f4';

                // --- AJAX Call to update stage ---
                const leadId = draggedItem.dataset.leadId;
                const newStage = this.dataset.stageKey;
                
                updateLeadStage(leadId, newStage);
            }
        });
    });

    function updateLeadStage(leadId, newStage) {
        const formData = new FormData();
        formData.append('action', 'update_lead_stage');
        formData.append('nonce', opbCrmAjax.nonce);
        formData.append('lead_id', leadId);
        formData.append('new_stage', newStage);

        fetch(opbCrmAjax.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Error:', data.data);
                // Optional: revert the drag & drop visually if the update fails
                alert('Could not update lead stage. Please try again.');
                // Note: a proper implementation would move the card back to its original column
            } else {
                console.log('Success:', data.data);
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}); 