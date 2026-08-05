<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forms Builder Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="admin.css" />
  </head>
  <body>
    <div class="admin-shell">
      <header class="admin-header">
        <div>
          <h1>Visual Form Admin</h1>
          <p>Редагування питань та перегляд зібраних відповідей</p>
        </div>
        <nav class="admin-nav">
          <a href="/index.php" class="ghost-link">До форми</a>
        </nav>
      </header>

      <div class="tabs">
        <button class="tab-btn active" data-tab="questions">Питання</button>
        <button class="tab-btn" data-tab="submissions">Дані</button>
      </div>

      <section id="tab-questions" class="tab-panel active">
        <div class="toolbar sticky" id="questions-toolbar">
          <button id="add-section-btn" class="primary-btn">+ Додати секцію</button>
          <button id="save-questions-btn" class="primary-btn">Зберегти questions.txt</button>
          <button id="collapse-all-btn" class="secondary-btn" type="button">Згорнути все</button>
          <button id="expand-all-btn" class="secondary-btn" type="button">Розгорнути все</button>
          <input
            id="question-filter"
            class="toolbar-input"
            type="text"
            placeholder="Пошук по секціях/питаннях..."
          />
          <span id="filter-stats" class="toolbar-note"></span>
        </div>
        <div id="sections-container"></div>
      </section>

      <section id="tab-submissions" class="tab-panel">
        <div class="toolbar">
          <button id="refresh-submissions-btn" class="primary-btn">Оновити дані</button>
          <button id="export-zip-btn" class="primary-btn" type="button">Експорт ZIP</button>
          <button id="select-all-columns-btn" class="secondary-btn" type="button">Обрати всі</button>
          <button id="clear-columns-btn" class="secondary-btn" type="button">Очистити вибір</button>
        </div>
        <div id="submissions-meta" class="meta"></div>
        <div id="export-columns-wrap" class="export-columns-wrap"></div>
        <div id="submissions-table-wrap" class="table-wrap"></div>
      </section>
    </div>

    <template id="question-card-template">
      <article class="question-card">
        <div class="card-grid">
          <label>
            Тип
            <select class="q-type">
              <option value="text">Text</option>
              <option value="longtext">Longtext</option>
              <option value="radio">Radio</option>
              <option value="checkbox">Checkbox</option>
              <option value="select">Select</option>
              <option value="date">Date</option>
              <option value="phone">Phone</option>
              <option value="photo">Photo</option>
            </select>
          </label>
          <label class="inline-check">
            <input type="checkbox" class="q-required" /> Обовʼязкове
          </label>
          <button type="button" class="danger-btn q-remove">Видалити питання</button>
        </div>

        <label>
          Питання
          <input type="text" class="q-question" />
        </label>

        <label>
          Пояснення
          <input type="text" class="q-explanation" />
        </label>

        <label class="q-photo-map-wrap hidden">
          Ім'я файлу для Photo брати з питання
          <input type="text" class="q-filename-from" placeholder='Наприклад: ПІБ' />
        </label>

        <label class="q-options-wrap hidden">
          Варіанти (кожен з нового рядка)
          <textarea class="q-options" rows="4"></textarea>
        </label>
      </article>
    </template>

    <script src="admin.js"></script>
  </body>
</html>
