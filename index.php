<html>
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="index.css" />
    <title>Local Storage</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link
      href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400&display=swap"
      rel="stylesheet"
    />
    <script src="jquery-3.7.1.min.js"></script>
    <script src="jquery.mask.min.js"></script>
  </head>
  <body>
    <header></header>
    <div class="main">
      <p class="heading">Вхідний контроль (Shark\Ram)</p>
      <p class="required">*Обов'язкове питання</p>
    </div>
    <div  id="dynamic-form">
      <?php include 'form_builder.php'; ?>
    </div>
    <script src="form_handler.js"></script>
  </body>
</html>
