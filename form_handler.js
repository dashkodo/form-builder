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
        if (!element) {
          return;
        }

        if (element.type === "checkbox" || element.type === "radio") {
          element.checked = Boolean(value);
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

function openPhotoCapture(fileInput) {
  if (!fileInput) {
    return;
  }

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    fileInput.click();
    return;
  }

  const overlay = document.createElement("div");
  overlay.className = "photo-capture-overlay";

  const panel = document.createElement("div");
  panel.className = "photo-capture-panel";

  const video = document.createElement("video");
  video.autoplay = true;
  video.playsInline = true;

  const previewImg = document.createElement("img");
  previewImg.className = "photo-capture-preview";
  previewImg.style.display = "none";

  const frame = document.createElement("div");
  frame.className = "photo-capture-frame";
  frame.appendChild(video);
  frame.appendChild(previewImg);

  const controls = document.createElement("div");
  controls.className = "photo-capture-controls";

  const captureBtn = document.createElement("button");
  captureBtn.type = "button";
  captureBtn.className = "btn";
  captureBtn.textContent = "Зробити кадр";

  const useBtn = document.createElement("button");
  useBtn.type = "button";
  useBtn.className = "btn";
  useBtn.textContent = "Використати фото";
  useBtn.style.display = "none";

  const retakeBtn = document.createElement("button");
  retakeBtn.type = "button";
  retakeBtn.className = "btn";
  retakeBtn.textContent = "Перезняти";
  retakeBtn.style.display = "none";

  const cancelBtn = document.createElement("button");
  cancelBtn.type = "button";
  cancelBtn.className = "btn";
  cancelBtn.textContent = "Скасувати";

  controls.appendChild(captureBtn);
  controls.appendChild(useBtn);
  controls.appendChild(retakeBtn);
  controls.appendChild(cancelBtn);
  panel.appendChild(frame);
  panel.appendChild(controls);
  overlay.appendChild(panel);
  document.body.appendChild(overlay);

  let stream = null;
  let processedFile = null;
  let previewUrl = "";

  const setMode = (mode) => {
    const isPreview = mode === "preview";
    if (isPreview) {
      video.style.display = "none";
    } else {
      video.style.display = "block";
    }
    previewImg.style.display = isPreview ? "block" : "none";
    captureBtn.style.display = isPreview ? "none" : "inline-block";
    useBtn.style.display = isPreview ? "inline-block" : "none";
    retakeBtn.style.display = isPreview ? "inline-block" : "none";
  };

  setMode("camera");

  const closeCapture = () => {
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
      stream = null;
    }
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
      previewUrl = "";
    }
    overlay.remove();
  };

  cancelBtn.addEventListener("click", closeCapture);
  overlay.addEventListener("click", (event) => {
    if (event.target === overlay) {
      closeCapture();
    }
  });

  captureBtn.addEventListener("click", async () => {
    if (!video.videoWidth || !video.videoHeight) {
      return;
    }

    captureBtn.disabled = true;

    const canvas = document.createElement("canvas");
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext("2d");
    if (!ctx) {
      captureBtn.disabled = false;
      closeCapture();
      return;
    }

    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const rawBlob = await new Promise((resolve) => {
      canvas.toBlob(resolve, "image/jpeg", 0.92);
    });

    if (!rawBlob) {
      captureBtn.disabled = false;
      closeCapture();
      return;
    }

    try {
      const rawFile = new File([rawBlob], `camera_${Date.now()}.jpg`, {
        type: "image/jpeg",
      });
      processedFile = await normalizePhotoFileToDocRatio(rawFile, "camera");
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
      }
      previewUrl = URL.createObjectURL(processedFile);
      previewImg.src = previewUrl;
      setMode("preview");
    } catch (e) {
      console.error("Capture preview generation failed", e);
      showNotification("Не вдалося обробити фото. Спробуйте ще раз.");
    } finally {
      captureBtn.disabled = false;
    }
  });

  retakeBtn.addEventListener("click", () => {
    processedFile = null;
    setMode("camera");
  });

  useBtn.addEventListener("click", () => {
    if (!processedFile) {
      return;
    }

    if (typeof DataTransfer === "function") {
      const transfer = new DataTransfer();
      transfer.items.add(processedFile);
      fileInput.files = transfer.files;
      fileInput.dataset.photoAlreadyProcessed = "1";
      fileInput.dispatchEvent(new Event("change", { bubbles: true }));
    } else {
      showNotification("Ваш браузер не підтримує попередній перегляд для камери. Оберіть фото файлом.");
    }
    closeCapture();
  });

  navigator.mediaDevices
    .getUserMedia({
      video: {
        facingMode: "user",
      },
      audio: false,
    })
    .then((mediaStream) => {
      stream = mediaStream;
      video.srcObject = stream;
      setMode("camera");
    })
    .catch(() => {
      closeCapture();
      showNotification("Немає доступу до камери. Оберіть фото вручну.");
      fileInput.click();
    });
}

function ensureSelectedPhotoPreview(fileInput) {
  if (!fileInput || !fileInput.parentElement) {
    return null;
  }

  let preview = fileInput.parentElement.querySelector(".photo-selected-preview");
  if (!preview) {
    preview = document.createElement("img");
    preview.className = "photo-selected-preview";
    preview.alt = "Фото попередній перегляд";
    preview.style.display = "none";
    fileInput.parentElement.appendChild(preview);
  }
  return preview;
}

function updateSelectedPhotoPreview(fileInput) {
  const preview = ensureSelectedPhotoPreview(fileInput);
  if (!preview) {
    return;
  }

  if (!fileInput.files || fileInput.files.length === 0) {
    if (preview.dataset.objectUrl) {
      URL.revokeObjectURL(preview.dataset.objectUrl);
      delete preview.dataset.objectUrl;
    }
    preview.removeAttribute("src");
    preview.style.display = "none";
    return;
  }

  const objectUrl = URL.createObjectURL(fileInput.files[0]);
  if (preview.dataset.objectUrl) {
    URL.revokeObjectURL(preview.dataset.objectUrl);
  }
  preview.dataset.objectUrl = objectUrl;
  preview.src = objectUrl;
  preview.style.display = "block";
}

function initPhotoCapture(formRoot) {
  if (!formRoot) {
    return;
  }

  formRoot.querySelectorAll(".photo-capture-trigger").forEach((button) => {
    if (button.dataset.captureBound === "1") {
      return;
    }

    button.dataset.captureBound = "1";
    button.addEventListener("click", () => {
      const targetInputId = button.dataset.targetInput;
      if (!targetInputId) {
        return;
      }

      const fileInput = document.getElementById(targetInputId);
      openPhotoCapture(fileInput);
    });
  });
}

function loadImageFromBlob(blob) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    const objectUrl = URL.createObjectURL(blob);
    img.onload = () => {
      URL.revokeObjectURL(objectUrl);
      resolve(img);
    };
    img.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      reject(new Error("Cannot load image"));
    };
    img.src = objectUrl;
  });
}

function cropImageToAspect(img, aspectWidth, aspectHeight) {
  const srcW = img.naturalWidth || img.width;
  const srcH = img.naturalHeight || img.height;
  const targetRatio = aspectWidth / aspectHeight;
  const srcRatio = srcW / srcH;

  let cropW = srcW;
  let cropH = srcH;
  let offsetX = 0;
  let offsetY = 0;

  if (srcRatio > targetRatio) {
    cropW = Math.floor(srcH * targetRatio);
    offsetX = Math.floor((srcW - cropW) / 2);
  } else if (srcRatio < targetRatio) {
    cropH = Math.floor(srcW / targetRatio);
    offsetY = Math.floor((srcH - cropH) / 2);
  }

  const canvas = document.createElement("canvas");
  canvas.width = cropW;
  canvas.height = cropH;
  const ctx = canvas.getContext("2d");
  if (!ctx) {
    return null;
  }

  ctx.drawImage(img, offsetX, offsetY, cropW, cropH, 0, 0, cropW, cropH);
  return canvas;
}

function canvasToFile(canvas, fileNameBase, mimeType, extension) {
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (!blob) {
          reject(new Error("Cannot convert image"));
          return;
        }
        resolve(
          new File([blob], `${fileNameBase || "photo"}.${extension}`, {
            type: mimeType,
          }),
        );
      },
      mimeType,
      0.92,
    );
  });
}

async function normalizePhotoFileToDocRatio(file, fileNameBase) {
  const img = await loadImageFromBlob(file);
  const croppedCanvas = cropImageToAspect(img, 3, 4);
  if (!croppedCanvas) {
    return file;
  }

  return canvasToFile(croppedCanvas, fileNameBase, "image/jpeg", "jpg");
}

async function enforcePhotoRatioOnInput(fileInput) {
  if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
    return;
  }

  if (fileInput.dataset.photoAlreadyProcessed === "1") {
    delete fileInput.dataset.photoAlreadyProcessed;
    updateSelectedPhotoPreview(fileInput);
    return;
  }

  const sourceFile = fileInput.files[0];
  if (!sourceFile.type.startsWith("image/")) {
    return;
  }

  try {
    const processedFile = await normalizePhotoFileToDocRatio(
      sourceFile,
      sourceFile.name.replace(/\.[^.]+$/, ""),
    );
    if (typeof DataTransfer === "function") {
      const transfer = new DataTransfer();
      transfer.items.add(processedFile);
      fileInput.files = transfer.files;
    }
    updateSelectedPhotoPreview(fileInput);
  } catch (e) {
    console.error("Photo ratio normalization failed", e);
    updateSelectedPhotoPreview(fileInput);
  }
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

  const payload = new FormData();
  dynamicForm.forEach((value, key) => {
    payload.append(key, value);
  });

  document.querySelectorAll('input[type="file"]').forEach((input) => {
    if (input.name && input.files && input.files.length > 0) {
      payload.append(input.name, input.files[0]);
    }
  });

  if (dynamicForm) {
    fetch("/save.php", {
      method: "POST",
      body: payload,
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
  initPhotoCapture(form);

  form.addEventListener("change", function (event) {
    const target = event.target;
    if (target.classList && target.classList.contains("photo-input")) {
      enforcePhotoRatioOnInput(target).then(() => {
        saveFormToStorage();
      });
      return;
    }

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
