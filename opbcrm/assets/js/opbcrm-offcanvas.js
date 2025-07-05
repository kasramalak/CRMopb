// OPBCRM Offcanvas Panel Logic
(function(){
  function openPanel() {
    var panel = document.getElementById('add-new-lead-panel');
    if(panel) {
      panel.classList.remove('translate-x-full','hidden');
      panel.classList.add('translate-x-0');
      // --- Populate Agent Dropdown ---
      var agentSelect = document.getElementById('agent_id');
      if(agentSelect) {
        agentSelect.innerHTML = '<option value="">Loading...</option>';
        jQuery.post(ajaxurl, {action: 'opbcrm_get_crm_agents'}, function(response) {
          if(response.success && response.data && response.data.agents) {
            var options = '<option value="">Select Agent</option>';
            response.data.agents.forEach(function(agent) {
              options += '<option value="'+agent.id+'">'+agent.name+'</option>';
            });
            agentSelect.innerHTML = options;
          } else {
            agentSelect.innerHTML = '<option value="">No agents found</option>';
          }
        });
      }
      // --- Populate Stage Dropdown ---
      var stageSelect = document.getElementById('lead-stage');
      if(stageSelect) {
        stageSelect.innerHTML = '<option value="">Loading...</option>';
        jQuery.post(ajaxurl, {action: 'opbcrm_get_lead_stages'}, function(response) {
          if(response.success && response.data && response.data.stages) {
            var options = '<option value="">Select Stage</option>';
            response.data.stages.forEach(function(stage) {
              options += '<option value="'+stage.id+'">'+stage.label+'</option>';
            });
            stageSelect.innerHTML = options;
          } else {
            stageSelect.innerHTML = '<option value="">No stages found</option>';
          }
        });
      }
      // --- Populate Property Dropdown (if exists) ---
      var propertySelect = document.getElementById('lead-proposal');
      if(propertySelect) {
        propertySelect.innerHTML = '<option value="">Loading...</option>';
        jQuery.post(ajaxurl, {action: 'opbcrm_get_properties'}, function(response) {
          if(response.success && response.data && response.data.properties) {
            var options = '<option value="">Select Property</option>';
            response.data.properties.forEach(function(prop) {
              options += '<option value="'+prop.id+'">'+prop.title+'</option>';
            });
            propertySelect.innerHTML = options;
          } else {
            propertySelect.innerHTML = '<option value="">No properties found</option>';
          }
        });
      }
      // --- Reset Substage ---
      var substageGroup = document.getElementById('lead-substage-group');
      var substageSelect = document.getElementById('lead-sub-stage');
      if(substageGroup && substageSelect) {
        substageGroup.classList.add('hidden');
        substageSelect.innerHTML = '';
      }
    }
  }
  function closePanel() {
    var panel = document.getElementById('add-new-lead-panel');
    if(panel) {
      panel.classList.add('translate-x-full');
      setTimeout(function(){ panel.classList.add('hidden'); }, 300);
      panel.classList.remove('translate-x-0');
    }
  }
  // Open button (should be triggered from All Leads page)
  window.opbcrmOpenAddLeadPanel = openPanel;
  // Close button
  document.addEventListener('click', function(e){
    if(e.target.classList.contains('opbcrm-offcanvas-close')) closePanel();
  });
  // Escape key
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') closePanel();
  });
})();

// --- AJAX Form Submission for Add New Lead ---
document.addEventListener('DOMContentLoaded', function() {
  var form = document.getElementById('add-new-lead-form');
  if(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      var submitBtn = form.querySelector('button[type="submit"]');
      var spinner = form.querySelector('.spinner');
      if(submitBtn) submitBtn.disabled = true;
      if(spinner) spinner.style.display = 'inline-block';
      var formData = new FormData(form);
      formData.append('action', 'opbcrm_add_new_lead');
      jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
          if(spinner) spinner.style.display = 'none';
          if(submitBtn) submitBtn.disabled = false;
          if(response.success) {
            showHouzcrmToast('Lead added successfully!', true);
            form.reset();
            closePanel();
            // Optionally, refresh the leads table or add the new lead dynamically
            if(typeof window.refreshLeadsTable === 'function') window.refreshLeadsTable();
          } else {
            showHouzcrmToast(response.data && response.data.message ? response.data.message : 'Error adding lead.', false);
          }
        },
        error: function() {
          if(spinner) spinner.style.display = 'none';
          if(submitBtn) submitBtn.disabled = false;
          showHouzcrmToast('An unexpected error occurred. Please try again.', false);
        }
      });
    });
  }
});

// --- Toast Notification ---
function showHouzcrmToast(msg, success) {
  var toast = document.createElement('div');
  toast.className = 'opbcrm-toast ' + (success ? 'success' : 'error');
  toast.textContent = msg;
  document.body.appendChild(toast);
  setTimeout(function(){ toast.classList.add('show'); }, 10);
  setTimeout(function(){ toast.classList.remove('show'); toast.remove(); }, 2600);
} 