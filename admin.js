const state = {
  sections: [],
  ui: {
    filterText: "",
  },
  submissions: {
    headers: [],
    rows: [],
    selectedColumns: new Set(),
  },
};

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function notify(message) {
  window.alert(message);
}

function showTab(tabName) {
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.classList.toggle("active", btn.dataset.tab === tabName);
  });

  document.querySelectorAll(".tab-panel").forEach((panel) => {
    panel.classList.toggle("active", panel.id === `tab-${tabName}`);
  });
}

function createDefaultQuestion() {
  return {
    type: "text",
    required: false,
    question: "",
    explanation: "",
    options: [],
    filenameFrom: "",
    folder: "",
  };
}

function createDefaultSection() {
  return {
    name: "Нова секція",
    questions: [createDefaultQuestion()],
    __collapsed: false,
  };
}

function sectionMatchesFilter(section, filterText) {
  if (!filterText) {
    return section.questions.map(() => true);
  }

  const sectionName = String(section.name || "").toLowerCase();
  if (sectionName.includes(filterText)) {
    return section.questions.map(() => true);
  }

  return section.questions.map((q) => {
    const text = [
      q.type,
      q.question,
      q.explanation,
      q.filenameFrom,
      ...(q.options || []),
    ]
      .join(" ")
      .toLowerCase();
    return text.includes(filterText);
  });
}

function updateFilterStats(visibleSections, visibleQuestions) {
  const totalSections = state.sections.length;
  const totalQuestions = state.sections.reduce(
    (acc, s) => acc + s.questions.length,
    0,
  );
  const stats = document.getElementById("filter-stats");
  if (!stats) {
    return;
  }
  if (state.ui.filterText) {
    stats.textContent = `Показано: секцій ${visibleSections}/${totalSections}, питань ${visibleQuestions}/${totalQuestions}`;
    return;
  }
  stats.textContent = `Секцій: ${totalSections}, питань: ${totalQuestions}`;
}

function needsOptions(type) {
  return ["radio", "checkbox", "select"].includes(type);
}

function renderSections() {
  const container = document.getElementById("sections-container");
  container.innerHTML = "";
  const filterText = state.ui.filterText.trim().toLowerCase();
  let visibleSectionCount = 0;
  let visibleQuestionCount = 0;

  state.sections.forEach((section, sectionIndex) => {
    const questionMatches = sectionMatchesFilter(section, filterText);
    const hasVisibleQuestion = questionMatches.some(Boolean);
    if (!hasVisibleQuestion) {
      return;
    }

    visibleSectionCount++;

    const sectionEl = document.createElement("section");
    sectionEl.className = "section-card";

    const visibleInSection = questionMatches.filter(Boolean).length;
    visibleQuestionCount += visibleInSection;
    const collapsed = Boolean(section.__collapsed) && !filterText;
    sectionEl.classList.toggle("collapsed", collapsed);

    sectionEl.innerHTML = `
      <div class="section-head">
        <label>
          Назва секції
          <input class="section-name" data-section-index="${sectionIndex}" value="${escapeHtml(section.name || "")}" />
        </label>
        <span class="section-counter">Питань: ${visibleInSection}/${section.questions.length}</span>
        <button type="button" class="secondary-btn toggle-section" data-section-index="${sectionIndex}">${collapsed ? "Розгорнути" : "Згорнути"}</button>
        <button type="button" class="secondary-btn add-question" data-section-index="${sectionIndex}">+ Питання</button>
        <button type="button" class="danger-btn remove-section" data-section-index="${sectionIndex}">Видалити секцію</button>
      </div>
      <div class="collapsed-note">Секція згорнута. Натисни "Розгорнути" щоб редагувати.</div>
      <div class="questions-list"></div>
    `;

    const list = sectionEl.querySelector(".questions-list");
    section.questions.forEach((q, qIndex) => {
      if (!questionMatches[qIndex]) {
        return;
      }

      const tpl = document.getElementById("question-card-template");
      const fragment = tpl.content.cloneNode(true);
      const card = fragment.querySelector(".question-card");

      const typeEl = card.querySelector(".q-type");
      const requiredEl = card.querySelector(".q-required");
      const questionEl = card.querySelector(".q-question");
      const explanationEl = card.querySelector(".q-explanation");
      const optionsWrap = card.querySelector(".q-options-wrap");
      const optionsEl = card.querySelector(".q-options");
      const photoWrap = card.querySelector(".q-photo-map-wrap");
      const filenameFromEl = card.querySelector(".q-filename-from");

      card.dataset.sectionIndex = String(sectionIndex);
      card.dataset.questionIndex = String(qIndex);

      typeEl.value = q.type || "text";
      requiredEl.checked = Boolean(q.required);
      questionEl.value = q.question || "";
      explanationEl.value = q.explanation || "";
      optionsEl.value = (q.options || []).join("\n");
      filenameFromEl.value = q.filenameFrom || "";

      optionsWrap.classList.toggle("hidden", !needsOptions(typeEl.value));
      photoWrap.classList.toggle("hidden", typeEl.value !== "photo");

      typeEl.addEventListener("change", () => {
        updateQuestionFromCard(card);
        optionsWrap.classList.toggle("hidden", !needsOptions(typeEl.value));
        photoWrap.classList.toggle("hidden", typeEl.value !== "photo");
      });

      card.querySelectorAll("input, textarea").forEach((input) => {
        input.addEventListener("input", () => updateQuestionFromCard(card));
      });

      card.querySelector(".q-remove").addEventListener("click", () => {
        state.sections[sectionIndex].questions.splice(qIndex, 1);
        renderSections();
      });

      list.appendChild(fragment);
    });

    container.appendChild(sectionEl);
  });

  updateFilterStats(visibleSectionCount, visibleQuestionCount);

  container.querySelectorAll(".section-name").forEach((input) => {
    input.addEventListener("input", (event) => {
      const idx = Number(event.target.dataset.sectionIndex);
      state.sections[idx].name = event.target.value;
      renderSections();
    });
  });

  container.querySelectorAll(".toggle-section").forEach((btn) => {
    btn.addEventListener("click", () => {
      const idx = Number(btn.dataset.sectionIndex);
      state.sections[idx].__collapsed = !state.sections[idx].__collapsed;
      renderSections();
    });
  });

  container.querySelectorAll(".add-question").forEach((btn) => {
    btn.addEventListener("click", () => {
      const idx = Number(btn.dataset.sectionIndex);
      state.sections[idx].questions.push(createDefaultQuestion());
      renderSections();
    });
  });

  container.querySelectorAll(".remove-section").forEach((btn) => {
    btn.addEventListener("click", () => {
      const idx = Number(btn.dataset.sectionIndex);
      state.sections.splice(idx, 1);
      if (state.sections.length === 0) {
        state.sections.push(createDefaultSection());
      }
      renderSections();
    });
  });
}

function updateQuestionFromCard(card) {
  const sectionIndex = Number(card.dataset.sectionIndex);
  const questionIndex = Number(card.dataset.questionIndex);
  const q = state.sections[sectionIndex].questions[questionIndex];

  q.type = card.querySelector(".q-type").value;
  q.required = card.querySelector(".q-required").checked;
  q.question = card.querySelector(".q-question").value;
  q.explanation = card.querySelector(".q-explanation").value;
  q.filenameFrom = card.querySelector(".q-filename-from").value;

  const optionsRaw = card.querySelector(".q-options").value;
  q.options = optionsRaw
    .split("\n")
    .map((v) => v.trim())
    .filter((v) => v !== "");
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, options);
  const json = await response.json();
  if (!json.ok) {
    throw new Error(json.error || "Unknown API error");
  }
  return json;
}

async function loadQuestions() {
  const json = await fetchJson("/admin_api.php?action=get_questions");
  state.sections = json.sections.map((section) => ({
    ...section,
    __collapsed: Boolean(section.__collapsed),
  }));
  if (!Array.isArray(state.sections) || state.sections.length === 0) {
    state.sections = [createDefaultSection()];
  }
  renderSections();
}

function validateSections() {
  for (const section of state.sections) {
    if (!section.name || section.name.trim() === "") {
      throw new Error("Кожна секція повинна мати назву");
    }
    for (const q of section.questions) {
      if (!q.question || q.question.trim() === "") {
        throw new Error("Кожне питання повинно містити текст питання");
      }
      if (q.type === "photo" && q.filenameFrom && q.filenameFrom.trim() === "") {
        throw new Error("Невірний filenameFrom у Photo");
      }
    }
  }
}

async function saveQuestions() {
  validateSections();
  await fetchJson("/admin_api.php?action=save_questions", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ sections: state.sections }),
  });
  notify("questions.txt збережено");
}

function renderSubmissionsTable(headers, rows) {
  const wrap = document.getElementById("submissions-table-wrap");
  if (!headers.length) {
    wrap.innerHTML = "<p>Поки що немає збережених даних.</p>";
    return;
  }

  let html = "<table class=\"sub-table\"><thead><tr>";
  html += "<th>#</th>";
  headers.forEach((h) => {
    html += `<th>${escapeHtml(h)}</th>`;
  });
  html += "</tr></thead><tbody>";

  rows.forEach((row, idx) => {
    html += "<tr>";
    html += `<td>${idx + 1}</td>`;
    headers.forEach((h) => {
      const val = row[h] || "";
      const photoUrl = row[`${h}__photoUrl`];
      if (photoUrl) {
        html += `<td><a href=\"/${photoUrl}\" target=\"_blank\">${escapeHtml(val)}</a><br/><img class=\"photo-thumb\" src=\"/${photoUrl}\" alt=\"photo\"/></td>`;
      } else {
        html += `<td>${escapeHtml(val)}</td>`;
      }
    });
    html += "</tr>";
  });

  html += "</tbody></table>";
  wrap.innerHTML = html;
}

function renderExportColumns() {
  const wrap = document.getElementById("export-columns-wrap");
  if (!wrap) {
    return;
  }

  const headers = state.submissions.headers || [];
  if (!headers.length) {
    wrap.innerHTML = "";
    return;
  }

  const chips = headers
    .map((h) => {
      const checked = state.submissions.selectedColumns.has(h) ? "checked" : "";
      return `<label class="col-chip"><input type="checkbox" class="export-col-check" data-col="${escapeHtml(h)}" ${checked}/> ${escapeHtml(h)}</label>`;
    })
    .join("");

  wrap.innerHTML = `<div class="export-columns-title">Колонки для експорту:</div><div class="export-columns-list">${chips}</div>`;

  wrap.querySelectorAll(".export-col-check").forEach((checkbox) => {
    checkbox.addEventListener("change", () => {
      const col = checkbox.dataset.col;
      if (!col) {
        return;
      }
      if (checkbox.checked) {
        state.submissions.selectedColumns.add(col);
      } else {
        state.submissions.selectedColumns.delete(col);
      }
    });
  });
}

async function exportZip() {
  if (!state.submissions.headers.length) {
    notify("Немає даних для експорту");
    return;
  }

  const columns = Array.from(state.submissions.selectedColumns);
  if (!columns.length) {
    notify("Оберіть хоча б одну колонку");
    return;
  }

  const response = await fetch("/admin_api.php?action=export_zip", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ columns }),
  });

  if (!response.ok) {
    let msg = "Помилка експорту";
    try {
      const err = await response.json();
      if (err && err.error) {
        msg = err.error;
      }
    } catch {
      // no-op
    }
    throw new Error(msg);
  }

  const blob = await response.blob();
  let filename = "form_export.zip";
  const disposition = response.headers.get("Content-Disposition") || "";
  const match = disposition.match(/filename="([^"]+)"/i);
  if (match && match[1]) {
    filename = match[1];
  }

  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

async function loadSubmissions() {
  const json = await fetchJson("/admin_api.php?action=get_submissions");
  const { submissions, storageDir } = json;
  state.submissions.headers = submissions.headers || [];
  state.submissions.rows = submissions.rows || [];
  if (state.submissions.selectedColumns.size === 0) {
    state.submissions.headers.forEach((h) => state.submissions.selectedColumns.add(h));
  } else {
    state.submissions.selectedColumns = new Set(
      Array.from(state.submissions.selectedColumns).filter((h) => state.submissions.headers.includes(h)),
    );
    if (state.submissions.selectedColumns.size === 0) {
      state.submissions.headers.forEach((h) => state.submissions.selectedColumns.add(h));
    }
  }

  document.getElementById("submissions-meta").textContent = `Storage: ${storageDir}`;
  renderSubmissionsTable(submissions.headers || [], submissions.rows || []);
  renderExportColumns();
}

function bindEvents() {
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.addEventListener("click", () => showTab(btn.dataset.tab));
  });

  document.getElementById("add-section-btn").addEventListener("click", () => {
    state.sections.push(createDefaultSection());
    renderSections();
  });

  document.getElementById("collapse-all-btn").addEventListener("click", () => {
    state.sections.forEach((s) => {
      s.__collapsed = true;
    });
    renderSections();
  });

  document.getElementById("expand-all-btn").addEventListener("click", () => {
    state.sections.forEach((s) => {
      s.__collapsed = false;
    });
    renderSections();
  });

  document.getElementById("question-filter").addEventListener("input", (event) => {
    state.ui.filterText = event.target.value || "";
    renderSections();
  });

  document.getElementById("save-questions-btn").addEventListener("click", async () => {
    try {
      await saveQuestions();
    } catch (e) {
      notify(e.message);
    }
  });

  document
    .getElementById("refresh-submissions-btn")
    .addEventListener("click", async () => {
      try {
        await loadSubmissions();
      } catch (e) {
        notify(e.message);
      }
    });

  document.getElementById("select-all-columns-btn").addEventListener("click", () => {
    state.submissions.selectedColumns = new Set(state.submissions.headers);
    renderExportColumns();
  });

  document.getElementById("clear-columns-btn").addEventListener("click", () => {
    state.submissions.selectedColumns = new Set();
    renderExportColumns();
  });

  document.getElementById("export-zip-btn").addEventListener("click", async () => {
    try {
      await exportZip();
    } catch (e) {
      notify(e.message || "Помилка експорту");
    }
  });
}

async function init() {
  bindEvents();
  try {
    await loadQuestions();
  } catch (e) {
    notify(`Не вдалося завантажити питання: ${e.message}`);
    state.sections = [createDefaultSection()];
    renderSections();
  }

  try {
    await loadSubmissions();
  } catch (e) {
    notify(`Не вдалося завантажити дані: ${e.message}`);
  }
}

document.addEventListener("DOMContentLoaded", init);
