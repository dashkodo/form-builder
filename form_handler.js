let currentSection = 0;
const STORAGE_KEY = "form_draft_data";
const STORAGE_SECTION = "form_current_section";

function formatPhoneUaValue(value) {
  const digits = (value || "").replace(/\D/g, "");
  const localDigits = digits.startsWith("38") ? digits.slice(2) : digits;
  const clipped = localDigits.slice(0, 10);

  if (clipped.length === 0) {
    return "";
  }

  if (clipped.length <= 3) {
    return `+38 ${clipped}`;
  }

  if (clipped.length <= 6) {
    return `+38 ${clipped.slice(0, 3)} ${clipped.slice(3)}`;
  }

  if (clipped.length <= 8) {
    return `+38 ${clipped.slice(0, 3)} ${clipped.slice(3, 6)} ${clipped.slice(6)}`;
  }

  return `+38 ${clipped.slice(0, 3)} ${clipped.slice(3, 6)} ${clipped.slice(6, 8)} ${clipped.slice(8, 10)}`;
}

function normalizePhoneInput(input) {
  if (!input || !input.classList.contains("phone-input")) {
    return;
  }
  input.value = formatPhoneUaValue(input.value);
}

function applyPhoneMask(form) {
  if (!form) {
    return;
  }

  if (window.jQuery && window.jQuery.fn && window.jQuery.fn.mask) {
    window.jQuery(form)
      .find(".phone-input")
      .mask("+38 000 000 00 00", {
        clearIfNotMatch: true,
        placeholder: "+38 ___ ___ __ __",
      });
    return;
  }

  form.querySelectorAll(".phone-input").forEach((input) => {
    normalizePhoneInput(input);
  });
}

function saveFormToStorage() {
  const forms = document.querySelectorAll("form");
  const formData = {};

  forms.forEach((form, formIndex) => {
    formData[formIndex] = {};
    form.querySelectorAll("input, textarea, select").forEach((element) => {
      if (!element.name) return;

      if (element.type === "checkbox" || element.type === "radio") {
        if (element.checked) {
          formData[formIndex][element.id] = element.checked;
        }
      } else if (element.value) {
        formData[formIndex][element.id] = element.value;
      }
    });
  });

  localStorage.setItem(STORAGE_KEY, JSON.stringify(formData));
  localStorage.setItem(STORAGE_SECTION, currentSection);
}

function loadFormFromStorage() {
  const stored = localStorage.getItem(STORAGE_KEY);
  const storedSection = 0;

  if (!stored) return false;

  try {
    const formData = JSON.parse(stored);
    const forms = document.querySelectorAll("form");

    forms.forEach((form, formIndex) => {
      if (!formData[formIndex]) return;

      Object.entries(formData[formIndex]).forEach(([id, value]) => {
        const element = form.querySelector(`#${id}`);

        if (element.type === "checkbox" || element.type === "radio") {
          element.checked = element.value;
        } else {
          element.value = value;
        }
        element.id.indexOf("free") !== -1 && element.value !== ""
          ? (element.style.display = "inline-block")
          : null;
      });
    });

    if (storedSection) {
      currentSection = parseInt(storedSection);
      updateSectionVisibility();
    }

    showNotification("Форма відновлена з попередньої сесії");
    return true;
  } catch (e) {
    console.error("Error loading form from storage:", e);
    return false;
  }
}

function clearFormStorage() {
  localStorage.removeItem(STORAGE_KEY);
  localStorage.removeItem(STORAGE_SECTION);
}

function updateSectionVisibility() {
  const sections = document.querySelectorAll("form");
  sections.forEach((section, index) => {
    section.style.display = index === currentSection ? "block" : "none";
  });
  window.scrollTo(0, 0);
}

function showNotification(message) {
  const notification = document.createElement("div");
  notification.className = "notification";
  notification.textContent = message;
  notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #4285f4;
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = "slideOut 0.3s ease";
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

function addToSet(set, key, value, tryAdd = false) {
  if (!set.has(key)) {
    set.set(key, value);
    return;
  }

  if (tryAdd && set.get(key).split(", ").includes(value)) {
    return;
  }

  if (tryAdd || value === "") {
    return;
  }

  if (set.get(key) === "") {
    set.set(key, value);
    return;
  }

  set.set(key, set.get(key) + ", " + value);
}
function serialize(form) {
  if (!form || form.nodeName !== "FORM") {
    return;
  }
  var i,
    j,
    q = new Map();

  for (i = 0; i < form.elements.length; i++) {
    if (form.elements[i].name === "") {
      continue;
    }
    switch (form.elements[i].nodeName) {
      case "INPUT":
        switch (form.elements[i].type) {
          case "date":
          case "text":
          case "hidden":
          case "password":
          case "button":
          case "reset":
          case "submit":
            if (form.elements[i].style.display !== "none")
              addToSet(q, form.elements[i].name, form.elements[i].value);
            break;
          case "checkbox":
          case "radio":
            if (form.elements[i].checked) {
              addToSet(q, form.elements[i].name, form.elements[i].value);
            } else {
              addToSet(q, form.elements[i].name, "", true);
            }
            break;
          case "file":
            break;
        }
        break;
      case "TEXTAREA":
        addToSet(q, form.elements[i].name, form.elements[i].value);
        break;
      case "SELECT":
        switch (form.elements[i].type) {
          case "select-one":
            addToSet(q, form.elements[i].name, form.elements[i].value);
            break;
          case "select-multiple":
            for (j = form.elements[i].options.length - 1; j >= 0; j = j - 1) {
              if (form.elements[i].options[j].selected) {
                addToSet(
                  q,
                  form.elements[i].name,
                  form.elements[i].options[j].value,
                );
              }
            }
            break;
        }
        break;
      case "BUTTON":
        switch (form.elements[i].type) {
          case "reset":
          case "submit":
          case "button":
            addToSet(q, form.elements[i].name, form.elements[i].value);
            break;
        }
        break;
    }
  }
  return q;
}

function getCurrentSectionElement() {
  return document.querySelector(`[data-section="${currentSection}"]`);
}

function validateCurrentSection() {
  const currentSectionEl = getCurrentSectionElement();
  if (!currentSectionEl.checkValidity()) {
    currentSectionEl
      .querySelectorAll(".input-error")
      .forEach((el) => el.classList.remove("input-error"));
  }
  const inputs = currentSectionEl?.querySelectorAll("[required]") || [];
  let isValid = true;

  inputs.forEach((input) => {
    if (
      (input.type === "checkbox" &&
        !Array.from(
          input.parentElement.querySelectorAll("input[type='checkbox']"),
        ).some((a) => a.checked)) ||
      (input.type !== "checkbox" && input.checkValidity() === false)
    ) {
      isValid = false;
      input.classList.add("input-error");
    } else {
      input.classList.remove("input-error");
    }
  });

  return isValid;
}

function submitForm() {
  if (!validateCurrentSection()) {
    return;
  }

  saveFormToStorage();

  const dynamicForm = new Map(
    [...document.querySelectorAll("form")]
      .map((f) => serialize(f))
      .flatMap((a) => [...a]),
  );

  if (dynamicForm) {
    fetch("/save.php", {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(dynamicForm)),
      headers: {
        "Content-Type": "application/json",
      },
    }).then((response) => {
      if (response.status === 200) {
        clearFormStorage();

        document.location.href = "/saved.html";
      }
    });
  }
}

function nextSection() {
  if (!validateCurrentSection()) {
    return;
  }

  saveFormToStorage();

  const sections = document.querySelectorAll("form");

  if (currentSection < sections.length - 1) {
    sections[currentSection].style.display = "none";
    currentSection++;
    sections[currentSection].style.display = "block";
    window.scrollTo(0, 0);
  }
}

function prevSection() {
  saveFormToStorage();

  const sections = document.querySelectorAll("form");

  if (currentSection > 0) {
    sections[currentSection].style.display = "none";
    currentSection--;
    sections[currentSection].style.display = "block";
    window.scrollTo(0, 0);
  }
}

document.addEventListener("DOMContentLoaded", function () {
  loadFormFromStorage();

  const form = document.getElementById("dynamic-form");
  if (!form) return;

  applyPhoneMask(form);

  form.addEventListener("change", function (event) {
    const target = event.target;
    saveFormToStorage();

    if (target.classList.contains("radio-input")) {
      const questionId = target.name;
      const parent = target.parentElement
        .querySelectorAll(`input[type="text"]`)
        .forEach((input) => {
          input.style.display = "none";
          input.value = "";
        });

      const freeInput = target?.nextElementSibling;

      if (
        freeInput &&
        freeInput.tagName === "INPUT" &&
        freeInput.attributes["type"] &&
        freeInput.attributes["type"].value == "text"
      ) {
        freeInput.style.display = target.checked ? "inline-block" : "none";
        if (!target.checked) {
          freeInput.value = "";
        }
      }
    } else if (target.classList.contains("checkbox-input")) {
      const questionId = target.name.replace("[]", "");
      const freeInput = target?.nextElementSibling;
      if (
        freeInput &&
        freeInput.tagName === "INPUT" &&
        freeInput.attributes["type"] &&
        freeInput.attributes["type"].value == "text"
      ) {
        const isAnyChecked = form.querySelector(
          `input[name="${target.name}"]:checked`,
        );
        freeInput.style.display = isAnyChecked ? "inline-block" : "none";
        if (!isAnyChecked) {
          freeInput.value = "";
        }
      }
    }
  });

  form.addEventListener("input", function (event) {
    if (
      event.target.classList.contains("phone-input") &&
      !(window.jQuery && window.jQuery.fn && window.jQuery.fn.mask)
    ) {
      normalizePhoneInput(event.target);
    }
    saveFormToStorage();
  });
});

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", loadFormFromStorage);
} else {
  loadFormFromStorage();
}
