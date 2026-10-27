/**
 * app.js - Vanilla JavaScript Frontend per Salute & Dieta
 * Nessuna dipendenza esterna. Fetch API per comunicare col backend PHP/SQLite.
 */

function getLocalDateString() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

const App = {
  activeTab: 'diet',
  currentDate: getLocalDateString(),
  
  // Dati di cache locale
  dietData: null,
  planData: null,
  sportData: [],
  bpData: null,
  medsData: null,
  weightHistory: [],

  init() {
    this.setupDateSelectors();
    this.setupTabs();
    this.setupModals();
    this.setupForms();
    this.setupFoodAutocomplete();
    this.setupPrintButton();
    
    // Caricamento iniziale della prima vista
    this.loadCurrentTab();
  },

  setupDateSelectors() {
    this.currentDate = getLocalDateString();
    const dietDateInput = document.getElementById('dietDateInput');
    const medDateInput = document.getElementById('medDateInput');
    const sportDateInput = document.getElementById('sportDate');
    
    if (dietDateInput) {
      dietDateInput.value = this.currentDate;
      dietDateInput.addEventListener('change', (e) => {
        this.currentDate = e.target.value;
        this.loadDietData();
      });
    }

    if (sportDateInput) {
      sportDateInput.value = this.currentDate;
    }

    if (medDateInput) {
      medDateInput.value = this.currentDate;
      medDateInput.addEventListener('change', (e) => {
        this.loadMedications(e.target.value);
      });
    }

    // Set static default current time for weight
    const inputWeightTime = document.getElementById('inputWeightTime');
    if (inputWeightTime && !inputWeightTime.value) {
      const now = new Date();
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      inputWeightTime.value = `${hours}:${minutes}`;
    }

    // Set default times for sport
    const sportStartTime = document.getElementById('sportStartTime');
    const sportEndTime = document.getElementById('sportEndTime');
    if (sportStartTime && !sportStartTime.value) {
      const now = new Date();
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      sportStartTime.value = `${hours}:${minutes}`;
    }
    if (sportEndTime && !sportEndTime.value) {
      const end = new Date(Date.now() + 60 * 60 * 1000);
      const hours = String(end.getHours()).padStart(2, '0');
      const minutes = String(end.getMinutes()).padStart(2, '0');
      sportEndTime.value = `${hours}:${minutes}`;
    }

    // Set default datetime-local for BP
    const bpDateInput = document.getElementById('bpMeasuredAt');
    if (bpDateInput) {
      const now = new Date();
      const localIso = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
      bpDateInput.value = localIso;
    }
  },

  setupTabs() {
    const navButtons = document.querySelectorAll('.nav-btn');
    navButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const targetTab = btn.getAttribute('data-tab');
        this.switchTab(targetTab);
      });
    });
  },

  switchTab(tabId) {
    this.activeTab = tabId;
    
    // Aggiorna pulsanti tab
    document.querySelectorAll('.nav-btn').forEach(btn => {
      btn.classList.toggle('active', btn.getAttribute('data-tab') === tabId);
    });

    // Aggiorna pannelli
    document.querySelectorAll('.tab-pane').forEach(pane => {
      pane.classList.toggle('active', pane.id === `tab-${tabId}`);
    });

    this.loadCurrentTab();
  },

  loadCurrentTab() {
    switch (this.activeTab) {
      case 'diet':
        this.loadDietData();
        this.loadWeightHistory();
        break;
      case 'plan':
        this.loadMealPlan();
        break;
      case 'sport':
        this.loadSportLogs();
        break;
      case 'pressure':
        this.loadBloodPressure();
        break;
      case 'medications':
        const medDate = document.getElementById('medDateInput')?.value || this.currentDate;
        this.loadMedications(medDate);
        break;
      case 'report':
        this.prepareReportView();
        break;
    }
  },

  // -------------------------------------------------------------
  // SEZIONE 1: DIETA E PESO
  // -------------------------------------------------------------
  async loadDietData() {
    try {
      const date = document.getElementById('dietDateInput')?.value || this.currentDate;
      const res = await fetch(`api/diet.php?action=day_summary&date=${date}`);
      const json = await res.json();
      
      if (!json.success) throw new Error(json.error || 'Errore nel caricamento della dieta');

      this.dietData = json;
      this.renderDietSummary(json);
      this.renderFoodDiary(json.foods || []);
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  renderDietSummary(data) {
    // Weight of selected date
    const weightVal = data.weight ? `${data.weight.weight} kg` : '-- kg';
    const timeStr = data.weight?.measured_time ? ` ore ${data.weight.measured_time}` : '';
    const weightNote = data.weight?.notes ? `(${data.weight.notes})` : '';
    const todayElem = document.getElementById('todayWeightDisplay');
    if (todayElem) {
      todayElem.textContent = `${weightVal}${timeStr} ${weightNote}`.trim();
    }
  },

  renderFoodDiary(foods) {
    const container = document.getElementById('foodGroupsContainer');
    if (!container) return;

    const mealLabels = {
      'prima_colazione': 'Prima Colazione',
      'seconda_colazione': 'Seconda Colazione',
      'pranzo': 'Pranzo',
      'merenda': 'Merenda',
      'cena': 'Cena',
      'altro': 'Altro / Extra'
    };

    // Raggruppa cibi per pasto
    const grouped = {
      'prima_colazione': [],
      'seconda_colazione': [],
      'pranzo': [],
      'merenda': [],
      'cena': [],
      'altro': []
    };

    foods.forEach(f => {
      const type = grouped[f.meal_type] ? f.meal_type : 'altro';
      grouped[type].push(f);
    });

    let html = '';
    for (const [key, label] of Object.entries(mealLabels)) {
      const items = grouped[key];
      const mealGrams = items.reduce((sum, item) => sum + (item.grams !== null && item.grams !== '' ? Number(item.grams || 0) : 0), 0);

      html += `
        <div class="meal-group">
          <div class="meal-group-header">
            <span>${label} (${items.length} alimenti)</span>
            <span>${mealGrams > 0 ? mealGrams + ' gr' : ''}</span>
          </div>
          <div class="meal-items-list">
            ${items.length === 0 ? '<div style="color:var(--text-light);font-size:0.85rem;padding:0.4rem 0;">Nessun alimento registrato.</div>' : ''}
            ${items.map(item => {
              const grText = (item.grams !== null && item.grams !== undefined && item.grams !== '') ? `${item.grams}` : '-';
              const noteText = item.notes ? this.escapeHtml(item.notes) : '-';
              return `
              <div class="food-row">
                <div>
                  <strong>${this.escapeHtml(item.food_name)}</strong>
                  <div class="food-macros">
                    <span>gr: ${grText}</span>
                    <span>note: ${noteText}</span>
                  </div>
                </div>
                <button class="btn btn-sm btn-danger" onclick="App.deleteFood(${item.id})" title="Elimina alimento">✕</button>
              </div>
            `;
            }).join('')}
          </div>
        </div>
      `;
    }

    container.innerHTML = html;
  },

  async loadWeightHistory() {
    try {
      const res = await fetch('api/diet.php?action=weight_history&limit=15');
      const json = await res.json();
      if (!json.success) return;

      this.weightHistory = json.data;
      const tbody = document.getElementById('weightHistoryTableBody');
      if (!tbody) return;

      if (json.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Nessuna misurazione registrata.</td></tr>';
        return;
      }

      tbody.innerHTML = json.data.map((w, index) => {
        let diffBadge = '';
        if (index > 0) {
          const diff = (w.weight - json.data[index - 1].weight).toFixed(1);
          const color = diff > 0 ? '#ef4444' : (diff < 0 ? '#10b981' : '#64748b');
          const sign = diff > 0 ? `+${diff}` : diff;
          diffBadge = `<small style="color:${color};font-weight:600;margin-left:6px;">(${sign} kg)</small>`;
        }

        return `
          <tr>
            <td><strong>${w.date}</strong></td>
            <td><small style="color:var(--text-muted);">${w.measured_time || '-'}</small></td>
            <td>${w.weight} kg ${diffBadge}</td>
            <td><small style="color:var(--text-muted);">${this.escapeHtml(w.notes || '-')}</small></td>
            <td style="text-align:right;">
              <button class="btn btn-sm btn-danger" onclick="App.deleteWeight(${w.id})">✕</button>
            </td>
          </tr>
        `;
      }).join('');
    } catch (err) {
      console.error(err);
    }
  },

  async deleteFood(id) {
    if (!confirm('Eliminare questo alimento dal diario?')) return;
    try {
      const res = await fetch('api/diet.php?action=delete_food', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);
      this.showToast('Alimento rimosso', 'success');
      this.loadDietData();
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async deleteWeight(id) {
    if (!confirm('Eliminare questa voce di peso?')) return;
    try {
      const res = await fetch('api/diet.php?action=delete_weight', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);
      this.showToast('Registrazione peso rimossa', 'success');
      this.loadDietData();
      this.loadWeightHistory();
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  // -------------------------------------------------------------
  // SEZIONE 2: PIANO ALIMENTARE SETTIMANALE
  // -------------------------------------------------------------
  async loadMealPlan() {
    try {
      const res = await fetch('api/plan.php');
      const json = await res.json();
      if (!json.success) throw new Error(json.error);

      this.planData = json.plan;
      this.renderMealPlan(json.plan);
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  renderMealPlan(plan) {
    const days = ['lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica'];
    const meals = [
      { key: 'prima_colazione', label: 'Prima Colazione (Alimenti)' },
      { key: 'seconda_colazione', label: 'Seconda Colazione (Alimenti)' },
      { key: 'pranzo', label: 'Pranzo (Alimenti)' },
      { key: 'merenda', label: 'Merenda' },
      { key: 'cena', label: 'Cena (Alimenti)' }
    ];

    const tbody = document.getElementById('mealPlanTableBody');
    if (!tbody) return;

    let html = '';
    meals.forEach(m => {
      html += `<tr>`;
      html += `<th class="meal-col-head"><strong>${m.label}</strong></th>`;
      
      days.forEach(d => {
        const cellData = (plan[d] && plan[d][m.key]) ? plan[d][m.key] : { food_items: '' };
        const content = this.escapeHtml(cellData.food_items || '');
        html += `
          <td>
            <div class="plan-cell-editor" 
                 contenteditable="true" 
                 data-day="${d}" 
                 data-meal="${m.key}"
                 data-placeholder="Clicca per inserire alimenti...">${content}</div>
          </td>
        `;
      });
      html += `</tr>`;
    });

    tbody.innerHTML = html;

    // Aggiungi listener per auto-salvataggio su blur
    tbody.querySelectorAll('.plan-cell-editor').forEach(cell => {
      cell.addEventListener('blur', () => {
        this.savePlanCell(cell);
      });
    });
  },

  async savePlanCell(cellElement) {
    const day = cellElement.getAttribute('data-day');
    const meal = cellElement.getAttribute('data-meal');
    const text = cellElement.innerText.trim();

    try {
      const res = await fetch('api/plan.php?action=save_cell', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          day_of_week: day,
          meal_type: meal,
          food_items: text
        })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);
      this.showToast('Piano pasto salvato', 'success');
    } catch (err) {
      this.showToast('Errore salvataggio piano: ' + err.message, 'error');
    }
  },

  async saveEntirePlan() {
    const editors = document.querySelectorAll('.plan-cell-editor');
    const items = [];
    editors.forEach(cell => {
      items.push({
        day_of_week: cell.getAttribute('data-day'),
        meal_type: cell.getAttribute('data-meal'),
        food_items: cell.innerText.trim()
      });
    });

    try {
      const res = await fetch('api/plan.php?action=save_all', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);
      this.showToast('Tutto il piano settimanale è stato salvato!', 'success');
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  // -------------------------------------------------------------
  // SEZIONE 3: MONITORAGGIO PRESSIONE ARTERIOSA (PA)
  // -------------------------------------------------------------
  async loadBloodPressure() {
    try {
      const res = await fetch('api/pressure.php?action=list&limit=30');
      const json = await res.json();
      if (!json.success) throw new Error(json.error);

      this.bpData = json.data;
      
      // Update Stats
      document.getElementById('bpAvgSys').textContent = json.stats.avg_systolic || '--';
      document.getElementById('bpAvgDia').textContent = json.stats.avg_diastolic || '--';
      document.getElementById('bpAvgPulse').textContent = json.stats.avg_pulse || '--';

      const tbody = document.getElementById('bpTableBody');
      if (!tbody) return;

      if (json.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Nessuna misurazione pressoria registrata.</td></tr>';
        return;
      }

      tbody.innerHTML = json.data.map(p => {
        const badge = this.getBPBadge(p.systolic, p.diastolic);
        return `
          <tr>
            <td><strong>${p.measured_at}</strong></td>
            <td><strong style="font-size:1.05rem;">${p.systolic}</strong> / ${p.diastolic} <small>mmHg</small></td>
            <td>${p.pulse} <small>bpm</small></td>
            <td>${badge}</td>
            <td><small style="color:var(--text-muted);">${this.escapeHtml(p.notes || '-')}</small></td>
            <td style="text-align:right;">
              <button class="btn btn-sm btn-danger" onclick="App.deleteBP(${p.id})">✕</button>
            </td>
          </tr>
        `;
      }).join('');
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  getBPBadge(sys, dia) {
    if (sys < 120 && dia < 80) {
      return '<span class="bp-badge bp-optimal">Ottimale</span>';
    } else if (sys <= 129 && dia <= 84) {
      return '<span class="bp-badge bp-normal">Normale</span>';
    } else if (sys <= 139 || dia <= 89) {
      return '<span class="bp-badge bp-high-normal">Normale-Alta</span>';
    } else if (sys <= 159 || dia <= 99) {
      return '<span class="bp-badge bp-hyper1">Ipertensione Gr.1</span>';
    } else {
      return '<span class="bp-badge bp-hyper2">Ipertensione Gr.2</span>';
    }
  },

  async deleteBP(id) {
    if (!confirm('Rimuovere questa misurazione della pressione?')) return;
    try {
      const res = await fetch('api/pressure.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);
      this.showToast('Misurazione eliminata', 'success');
      this.loadBloodPressure();
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  // -------------------------------------------------------------
  // SEZIONE 4: REGISTRO FARMACI E SOMMINISTRAZIONI
  // -------------------------------------------------------------
  async loadMedications(date) {
    try {
      const targetDate = date || document.getElementById('medDateInput')?.value || this.currentDate;
      const res = await fetch(`api/medications.php?action=daily_status&date=${targetDate}`);
      const json = await res.json();
      if (!json.success) throw new Error(json.error);

      this.medsData = json.data;
      this.renderMedicationsChecklist(json.data, targetDate);
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  renderMedicationsChecklist(meds, date) {
    const container = document.getElementById('medsChecklistContainer');
    if (!container) return;

    if (meds.length === 0) {
      container.innerHTML = `
        <div style="text-align:center;padding:2rem;color:var(--text-muted);background:#ffffff;border-radius:var(--radius-md);border:1px solid var(--border-color);">
          Nessun farmaco in terapia. Clicca su <strong>"+ Nuovo Farmaco"</strong> per aggiungerne uno.
        </div>
      `;
      return;
    }

    let takenCount = 0;
    container.innerHTML = meds.map(m => {
      const isTaken = m.intake_status == 1;
      if (isTaken) takenCount++;

      return `
        <div class="med-item-card ${isTaken ? 'taken' : ''}" id="med-card-${m.id}">
          <div class="med-left">
            <input type="checkbox" 
                   class="med-checkbox" 
                   id="chk-med-${m.id}" 
                   ${isTaken ? 'checked' : ''} 
                   onchange="App.toggleMedIntake(${m.id}, '${date}', this.checked)">
            <div class="med-info">
              <h4>${this.escapeHtml(m.name)}</h4>
              <div class="med-sub">
                <span class="med-time-badge">⏰ ${m.scheduled_time}</span>
                <span>💊 ${this.escapeHtml(m.dosage)}</span>
                ${m.notes ? `<span>• ${this.escapeHtml(m.notes)}</span>` : ''}
              </div>
            </div>
          </div>
          <div>
            <button class="btn btn-sm btn-secondary" onclick="App.openEditMedModal(${JSON.stringify(m).replace(/"/g, '&quot;')})">Modifica</button>
            <button class="btn btn-sm btn-danger" onclick="App.deleteMedication(${m.id})">✕</button>
          </div>
        </div>
      `;
    }).join('');

    // Update med daily counter
    const counterBadge = document.getElementById('medStatusCount');
    if (counterBadge) {
      counterBadge.textContent = `${takenCount} / ${meds.length} Assunti`;
      counterBadge.className = `bp-badge ${takenCount === meds.length ? 'bp-optimal' : 'bp-high-normal'}`;
    }
  },

  async toggleMedIntake(medId, date, isChecked) {
    try {
      const res = await fetch('api/medications.php?action=toggle_intake', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          medication_id: medId,
          date: date,
          status: isChecked ? 1 : 0
        })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);

      const card = document.getElementById(`med-card-${medId}`);
      if (card) {
        card.classList.toggle('taken', isChecked);
      }
      this.showToast(isChecked ? 'Farmaco segnato come assunto' : 'Spunta assunzione rimossa', 'success');
      this.loadMedications(date);
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async deleteMedication(id) {
    if (!confirm('Eliminare definitivamente questo farmaco dalla terapia?')) return;
    try {
      const res = await fetch('api/medications.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);
      this.showToast('Farmaco eliminato', 'success');
      this.loadMedications();
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  // -------------------------------------------------------------
  // SEZIONE: SPORT & ATTIVITÀ
  // -------------------------------------------------------------
  async loadSportLogs() {
    try {
      const res = await fetch('api/sport.php?action=list&limit=50');
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Errore nel caricamento delle attività');

      this.sportData = json.data || [];
      const tbody = document.getElementById('sportHistoryTableBody');
      if (!tbody) return;

      if (this.sportData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">Nessuna attività registrata.</td></tr>';
        return;
      }

      tbody.innerHTML = this.sportData.map(s => `
        <tr>
          <td><strong>${s.date}</strong></td>
          <td>${s.start_time || '-'} - ${s.end_time || '-'}</td>
          <td><strong>${this.escapeHtml(s.activity)}</strong></td>
          <td><small style="color:var(--text-muted);">${this.escapeHtml(s.notes || '-')}</small></td>
          <td style="text-align:right;">
            <button class="btn btn-sm btn-danger" onclick="App.deleteSport(${s.id})" title="Elimina">✕</button>
          </td>
        </tr>
      `).join('');
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async deleteSport(id) {
    if (!confirm('Eliminare questa sessione di attività sportiva?')) return;
    try {
      const res = await fetch('api/sport.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error);
      this.showToast('Attività rimossa', 'success');
      this.loadSportLogs();
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  // -------------------------------------------------------------
  // GESTIONE FORM & MODALI
  // -------------------------------------------------------------
  setupForms() {
    // Form Peso
    document.getElementById('formWeight')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const date = document.getElementById('dietDateInput')?.value || this.currentDate;
      const weight = document.getElementById('inputWeight').value;
      const measured_time = document.getElementById('inputWeightTime')?.value || '';
      const notes = document.getElementById('inputWeightNotes').value;

      try {
        const res = await fetch('api/diet.php?action=save_weight', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ date, weight, measured_time, notes })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.error);

        this.showToast('Peso salvato con successo', 'success');
        document.getElementById('inputWeight').value = '';
        document.getElementById('inputWeightNotes').value = '';
        this.loadDietData();
        this.loadWeightHistory();
      } catch (err) {
        this.showToast(err.message, 'error');
      }
    });

    // Form Alimento
    document.getElementById('formFood')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const date = document.getElementById('dietDateInput')?.value || this.currentDate;
      const payload = {
        date: date,
        meal_type: document.getElementById('foodMealType').value,
        food_name: document.getElementById('foodName').value,
        grams: document.getElementById('foodGrams')?.value || null,
        notes: document.getElementById('foodNotes')?.value || ''
      };

      try {
        const res = await fetch('api/diet.php?action=add_food', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.error);

        this.showToast('Alimento aggiunto', 'success');
        // Reset solo campi dell'alimento
        document.getElementById('foodName').value = '';
        if (document.getElementById('foodGrams')) document.getElementById('foodGrams').value = '';
        if (document.getElementById('foodNotes')) document.getElementById('foodNotes').value = '';
        this.fetchFoodSuggestions();
        this.loadDietData();
      } catch (err) {
        this.showToast(err.message, 'error');
      }
    });

    // Form Sport
    document.getElementById('formSport')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {
        date: document.getElementById('sportDate').value,
        start_time: document.getElementById('sportStartTime').value,
        end_time: document.getElementById('sportEndTime').value,
        activity: document.getElementById('sportActivity').value,
        notes: document.getElementById('sportNotes').value
      };

      try {
        const res = await fetch('api/sport.php?action=add', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.error);

        this.showToast('Attività sportiva registrata', 'success');
        document.getElementById('sportActivity').value = '';
        document.getElementById('sportNotes').value = '';
        this.loadSportLogs();
      } catch (err) {
        this.showToast(err.message, 'error');
      }
    });

    // Form Pressione Arteriosa
    document.getElementById('formPressure')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {
        measured_at: document.getElementById('bpMeasuredAt').value.replace('T', ' '),
        systolic: document.getElementById('bpSystolic').value,
        diastolic: document.getElementById('bpDiastolic').value,
        pulse: document.getElementById('bpPulse').value,
        notes: document.getElementById('bpNotes').value
      };

      try {
        const res = await fetch('api/pressure.php?action=add', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.error);

        this.showToast('Misurazione salvata', 'success');
        document.getElementById('bpSystolic').value = '';
        document.getElementById('bpDiastolic').value = '';
        document.getElementById('bpPulse').value = '';
        document.getElementById('bpNotes').value = '';
        this.loadBloodPressure();
      } catch (err) {
        this.showToast(err.message, 'error');
      }
    });

    // Form Farmaco (Nuovo o Modifica)
    document.getElementById('formMedication')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = document.getElementById('medId').value;
      const isEdit = Boolean(id);
      const action = isEdit ? 'update' : 'add';

      const payload = {
        id: id,
        name: document.getElementById('medName').value,
        dosage: document.getElementById('medDosage').value,
        scheduled_time: document.getElementById('medTime').value,
        notes: document.getElementById('medNotes').value
      };

      try {
        const res = await fetch(`api/medications.php?action=${action}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.error);

        this.showToast(isEdit ? 'Farmaco aggiornato' : 'Nuovo farmaco aggiunto', 'success');
        this.closeModal('modalMedication');
        this.loadMedications();
      } catch (err) {
        this.showToast(err.message, 'error');
      }
    });
  },

  setupModals() {
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const modal = e.target.closest('.modal-overlay');
        if (modal) modal.classList.remove('active');
      });
    });
  },

  openAddMedModal() {
    document.getElementById('medModalTitle').textContent = 'Aggiungi Nuovo Farmaco';
    document.getElementById('medId').value = '';
    document.getElementById('medName').value = '';
    document.getElementById('medDosage').value = '';
    document.getElementById('medTime').value = '08:00';
    document.getElementById('medNotes').value = '';
    
    document.getElementById('modalMedication').classList.add('active');
  },

  openEditMedModal(med) {
    document.getElementById('medModalTitle').textContent = 'Modifica Farmaco';
    document.getElementById('medId').value = med.id;
    document.getElementById('medName').value = med.name;
    document.getElementById('medDosage').value = med.dosage;
    document.getElementById('medTime').value = med.scheduled_time;
    document.getElementById('medNotes').value = med.notes || '';
    
    document.getElementById('modalMedication').classList.add('active');
  },

  closeModal(modalId) {
    document.getElementById(modalId)?.classList.remove('active');
  },

  setupFoodAutocomplete() {
    const foodInput = document.getElementById('foodName');
    if (!foodInput) return;

    let debounceTimer = null;
    foodInput.addEventListener('input', (e) => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        this.fetchFoodSuggestions(e.target.value);
      }, 250);
    });

    foodInput.addEventListener('focus', () => {
      this.fetchFoodSuggestions(foodInput.value);
    });

    // Initial fetch
    this.fetchFoodSuggestions();
  },

  async fetchFoodSuggestions(query = '') {
    const datalist = document.getElementById('foodSuggestions');
    if (!datalist) return;

    try {
      const qParam = query ? `&q=${encodeURIComponent(query)}` : '';
      const res = await fetch(`api/diet.php?action=food_suggestions${qParam}`);
      const json = await res.json();
      if (json.success && Array.isArray(json.data)) {
        datalist.innerHTML = json.data.map(item => `<option value="${this.escapeHtml(item)}"></option>`).join('');
      }
    } catch (e) {
      console.warn('Errore autocompletamento alimenti', e);
    }
  },

  // -------------------------------------------------------------
  // GENERAZIONE & STAMPA REPORT MEDICO
  // -------------------------------------------------------------
  setupPrintButton() {
    document.getElementById('btnPrintReport')?.addEventListener('click', () => {
      this.generateAndPrintReport();
    });

    document.getElementById('btnPreviewReport')?.addEventListener('click', () => {
      this.renderReportPreview();
    });
  },

  prepareReportView() {
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - 6);

    const startInput = document.getElementById('reportStartDate');
    const endInput = document.getElementById('reportEndDate');
    if (startInput && !startInput.value) startInput.value = start.toISOString().split('T')[0];
    if (endInput && !endInput.value) endInput.value = end.toISOString().split('T')[0];

    this.renderReportPreview();
  },

  async fetchReportData() {
    const startDate = document.getElementById('reportStartDate')?.value || new Date(Date.now() - 6*86400000).toISOString().split('T')[0];
    const endDate = document.getElementById('reportEndDate')?.value || new Date().toISOString().split('T')[0];

    const res = await fetch(`api/report.php?start_date=${startDate}&end_date=${endDate}`);
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Errore nel recupero dati report');
    return json;
  },

  async renderReportPreview() {
    try {
      const data = await this.fetchReportData();
      const previewContainer = document.getElementById('reportPreviewArea');
      if (!previewContainer) return;

      previewContainer.innerHTML = this.buildReportHtml(data);
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async generateAndPrintReport() {
    try {
      const data = await this.fetchReportData();
      const printArea = document.getElementById('printReportArea');
      if (!printArea) return;

      printArea.innerHTML = this.buildReportHtml(data, true);
      window.print();
    } catch (err) {
      this.showToast('Errore durante la generazione della stampa: ' + err.message, 'error');
    }
  },

  buildReportHtml(data, isForPrint = false) {
    const { range, weights, diet_summary, all_foods, pressures, bp_stats, medications, intake_map, meal_plan } = data;
    
    // Costruzione tabella Pressione
    let bpRowsHtml = '';
    if (pressures.length === 0) {
      bpRowsHtml = '<tr><td colspan="5" style="text-align:center;">Nessuna misurazione nel periodo.</td></tr>';
    } else {
      bpRowsHtml = pressures.map(p => `
        <tr>
          <td>${p.measured_at}</td>
          <td><strong>${p.systolic} / ${p.diastolic} mmHg</strong></td>
          <td>${p.pulse} bpm</td>
          <td>${this.getBPBadge(p.systolic, p.diastolic)}</td>
          <td>${this.escapeHtml(p.notes || '-')}</td>
        </tr>
      `).join('');
    }

    // Costruzione tabella Farmaci e Assunzioni
    let medRowsHtml = '';
    if (medications.length === 0) {
      medRowsHtml = '<tr><td colspan="4" style="text-align:center;">Nessun farmaco prescritto.</td></tr>';
    } else {
      medRowsHtml = medications.map(m => {
        return `
          <tr>
            <td><strong>${this.escapeHtml(m.name)}</strong></td>
            <td>${this.escapeHtml(m.dosage)}</td>
            <td>${m.scheduled_time}</td>
            <td>${this.escapeHtml(m.notes || '-')}</td>
          </tr>
        `;
      }).join('');
    }

    // Dieta & Peso
    let dietRowsHtml = '';
    if (diet_summary.length === 0 && weights.length === 0) {
      dietRowsHtml = '<tr><td colspan="6" style="text-align:center;">Nessuna voce registrata nel periodo.</td></tr>';
    } else {
      // Map all dates in range
      const dates = Array.from(new Set([...diet_summary.map(d => d.date), ...weights.map(w => w.date)])).sort();
      const weightMap = {};
      weights.forEach(w => { weightMap[w.date] = w; });
      const dietMap = {};
      diet_summary.forEach(d => { dietMap[d.date] = d; });

      dietRowsHtml = dates.map(d => {
        const w = weightMap[d];
        const di = dietMap[d];
        return `
          <tr>
            <td><strong>${d}</strong></td>
            <td>${w ? `<strong>${w.weight} kg</strong> ${w.notes ? `<small>(${w.notes})</small>` : ''}` : '-'}</td>
            <td>${di ? `${Math.round(di.total_cal)} kcal` : '-'}</td>
            <td>${di ? `${Math.round(di.total_carbs)}g` : '-'}</td>
            <td>${di ? `${Math.round(di.total_proteins)}g` : '-'}</td>
            <td>${di ? `${Math.round(di.total_fats)}g` : '-'}</td>
          </tr>
        `;
      }).join('');
    }

    return `
      <div class="print-header">
        <div>
          <h1>Riepilogo Clinico e Nutrizionale</h1>
          <p style="font-size:10pt;color:#374151;">Dossier Salute Personale per Medico Curante / Nutrizionista</p>
        </div>
        <div class="print-meta">
          <div><strong>Periodo:</strong> ${range.start} - ${range.end}</div>
          <div><strong>Generato il:</strong> ${new Date().toLocaleDateString('it-IT')} ore ${new Date().toLocaleTimeString('it-IT', {hour:'2-digit', minute:'2-digit'})}</div>
        </div>
      </div>

      <div class="print-section">
        <h2>1. Monitoraggio Pressione Arteriosa (PA) & Frequenza Cardiaca</h2>
        <div class="print-grid">
          <div class="print-stat-box">
            <small style="color:#6b7280;text-transform:uppercase;font-weight:bold;">Media Sistolica / Diastolica</small>
            <div style="font-size:14pt;font-weight:bold;color:#0f766e;">${bp_stats.avg_sys} / ${bp_stats.avg_dia} mmHg</div>
          </div>
          <div class="print-stat-box">
            <small style="color:#6b7280;text-transform:uppercase;font-weight:bold;">Media Pulsazioni & Conteggio</small>
            <div style="font-size:14pt;font-weight:bold;color:#0369a1;">${bp_stats.avg_pulse} bpm <span style="font-size:10pt;font-weight:normal;color:#6b7280;">(${bp_stats.count} misurazioni)</span></div>
          </div>
        </div>

        <table class="print-table">
          <thead>
            <tr>
              <th>Data e Ora</th>
              <th>Valore PA</th>
              <th>Pulsazioni</th>
              <th>Classificazione</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            ${bpRowsHtml}
          </tbody>
        </table>
      </div>

      <div class="print-section">
        <h2>2. Terapia Farmacologica & Aderenza Terapeutica</h2>
        <table class="print-table">
          <thead>
            <tr>
              <th>Nome Farmaco</th>
              <th>Dosaggio</th>
              <th>Orario Previsto</th>
              <th>Indicazioni / Istruzioni</th>
            </tr>
          </thead>
          <tbody>
            ${medRowsHtml}
          </tbody>
        </table>
      </div>

      <div class="print-section">
        <h2>3. Registro Nutrizionale & Storico Peso Corporeo</h2>
        <table class="print-table">
          <thead>
            <tr>
              <th>Data</th>
              <th>Peso Corporeo</th>
              <th>Calorie Tot.</th>
              <th>Carboidrati</th>
              <th>Proteine</th>
              <th>Grassi</th>
            </tr>
          </thead>
          <tbody>
            ${dietRowsHtml}
          </tbody>
        </table>
      </div>

      <div class="print-signature-area">
        <div class="sig-box">Firma Paziente</div>
        <div class="sig-box">Timbro e Firma del Medico / Nutrizionista</div>
      </div>
    `;
  },

  // -------------------------------------------------------------
  // UTILITY GLOBALI
  // -------------------------------------------------------------
  showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${type === 'success' ? '✓' : (type === 'error' ? '⚠️' : 'ℹ️')}</span> <span>${this.escapeHtml(message)}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  },

  escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
};

// Bootstrap app on DOM loaded
document.addEventListener('DOMContentLoaded', () => {
  App.init();
});
