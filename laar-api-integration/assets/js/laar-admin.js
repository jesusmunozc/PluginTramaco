/**
 * Laar Courier API - Scripts Admin
 * Panel de administración WordPress
 */

(function ($) {
  "use strict";

  var ciudadesData = [];

  /**
   * Inicialización
   */
  $(document).ready(function () {
    initTestConnection();
    initGenerarGuiaAdmin();
    initTrackingAdmin();
    loadCiudadesAdmin();
  });

  /**
   * Cargar ciudades para admin
   */
  function loadCiudadesAdmin() {
    var $select = $("#dest_ciudad");
    if (!$select.length) return;

    $select.html('<option value="">Cargando ciudades...</option>');

    $.ajax({
      url: laarAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "laar_ciudades",
      },
      success: function (response) {
        if (response.success && response.data) {
          ciudadesData = response.data;
          populateCiudades($select);
        }
      },
      error: function () {
        $select.html('<option value="">Error cargando ciudades</option>');
      },
    });
  }

  /**
   * Poblar ciudades
   */
  function populateCiudades($select) {
    $select.empty().append('<option value="">Seleccione ciudad...</option>');
    ciudadesData.forEach(function (ciudad) {
      $select.append(
        $("<option>", {
          value: ciudad.codigo,
          text: ciudad.nombre,
        })
      );
    });
  }

  /**
   * Probar conexión
   */
  function initTestConnection() {
    $("#test-connection").on("click", function () {
      var $btn = $(this);
      var $result = $("#connection-result");
      var $accountInfo = $("#account-info");
      var $productos = $("#productos-list");

      $btn.prop("disabled", true).text("Probando...");
      $result
        .removeClass("success error")
        .html('<div class="loading-spinner"></div> Conectando...');

      $.ajax({
        url: laarAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "laar_auth",
          nonce: laarAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            $result
              .addClass("success")
              .html("<strong>✓ Conexión exitosa!</strong>");

            // Mostrar info de cuenta
            displayAccountInfo($accountInfo, response.data);

            // Cargar productos
            loadProductos($productos);
          } else {
            $result
              .addClass("error")
              .html(
                "<strong>✗ Error:</strong> " +
                  (response.message || "No se pudo conectar")
              );
          }
        },
        error: function () {
          $result
            .addClass("error")
            .html("<strong>✗ Error de conexión</strong>");
        },
        complete: function () {
          $btn.prop("disabled", false).text("Probar Autenticación");
        },
      });
    });
  }

  /**
   * Mostrar info de cuenta
   */
  function displayAccountInfo($container, data) {
    var html = '<div class="account-info-grid">';

    if (data.username) {
      html += '<div class="account-info-item">';
      html += "<label>Usuario</label>";
      html += "<span>" + data.username + "</span>";
      html += "</div>";
    }

    if (data.ruc) {
      html += '<div class="account-info-item">';
      html += "<label>RUC</label>";
      html += "<span>" + data.ruc + "</span>";
      html += "</div>";
    }

    if (data.codigoUsuario) {
      html += '<div class="account-info-item">';
      html += "<label>Código Usuario</label>";
      html += "<span>" + data.codigoUsuario + "</span>";
      html += "</div>";
    }

    if (data.codigoSucursal) {
      html += '<div class="account-info-item">';
      html += "<label>Código Sucursal</label>";
      html += "<span>" + data.codigoSucursal + "</span>";
      html += "</div>";
    }

    html += "</div>";

    $container.addClass("success").html(html);
  }

  /**
   * Cargar productos
   */
  function loadProductos($container) {
    $.ajax({
      url: laarAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "laar_productos",
        nonce: laarAdmin.nonce,
      },
      success: function (response) {
        if (response.success && response.data) {
          var html = '<div class="productos-grid">';
          response.data.forEach(function (producto) {
            html += '<div class="producto-item">';
            html += '<div class="nombre">' + producto.nombre + "</div>";
            html += '<div class="codigo">' + producto.codigo + "</div>";
            html += "</div>";
          });
          html += "</div>";
          $container.html(html);
        }
      },
    });
  }

  /**
   * Formulario generar guía admin
   */
  function initGenerarGuiaAdmin() {
    $("#form-generar-guia").on("submit", function (e) {
      e.preventDefault();

      var $form = $(this);
      var $btn = $form.find('button[type="submit"]');
      var $result = $("#guia-result");

      var formData = $form.serializeArray();
      var data = {
        action: "laar_generar_guia",
        nonce: laarAdmin.nonce,
      };
      formData.forEach(function (item) {
        data[item.name] = item.value;
      });

      // Checkbox COD
      data.cod = $("#cod").is(":checked") ? "1" : "0";

      $btn.prop("disabled", true).text("Generando guía...");
      $result
        .removeClass("success error")
        .html('<div class="loading-spinner"></div> Procesando...');

      $.ajax({
        url: laarAdmin.ajaxUrl,
        type: "POST",
        data: data,
        success: function (response) {
          if (response.success && response.data) {
            if (response.data.numeroGuia) {
              $result
                .addClass("success")
                .html(
                  '<div class="guia-success">' +
                    "<h4>✓ Guía generada exitosamente</h4>" +
                    '<div class="guia-number">' +
                    response.data.numeroGuia +
                    "</div>" +
                    '<div class="guia-actions">' +
                    '<button type="button" class="button button-primary" onclick="window.print()">Imprimir</button> ' +
                    '<button type="button" class="button" id="btn-ver-pdf" data-guia="' +
                    response.data.numeroGuia +
                    '">Ver PDF</button>' +
                    "</div>" +
                    "</div>"
                );
              $form[0].reset();
            } else {
              $result
                .addClass("error")
                .html(
                  "<strong>Respuesta:</strong><pre>" +
                    JSON.stringify(response.data, null, 2) +
                    "</pre>"
                );
            }
          } else {
            $result
              .addClass("error")
              .html(
                "<strong>Error:</strong> " +
                  (response.message || "Error desconocido")
              );
          }
        },
        error: function (xhr) {
          $result.addClass("error").html("<strong>Error de conexión</strong>");
        },
        complete: function () {
          $btn.prop("disabled", false).text("Generar Guía");
        },
      });
    });
  }

  /**
   * Tracking admin
   */
  function initTrackingAdmin() {
    $("#form-tracking-admin").on("submit", function (e) {
      e.preventDefault();

      var $form = $(this);
      var $btn = $form.find('button[type="submit"]');
      var $result = $("#tracking-result");
      var guia = $("#numero_guia").val().trim();

      if (!guia) {
        $result.addClass("error").html("<p>Ingrese un número de guía</p>");
        return;
      }

      $btn.prop("disabled", true).text("Consultando...");
      $result
        .removeClass("success error")
        .html('<div class="loading-spinner"></div> Buscando...');

      $.ajax({
        url: laarAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "laar_tracking",
          guia: guia,
        },
        success: function (response) {
          if (response.success && response.data) {
            displayTrackingAdmin($result, response.data, guia);
          } else {
            $result
              .addClass("error")
              .html(
                "<p>No se encontró información para la guía: <strong>" +
                  guia +
                  "</strong></p>"
              );
          }
        },
        error: function () {
          $result.addClass("error").html("<p>Error de conexión</p>");
        },
        complete: function () {
          $btn.prop("disabled", false).text("Consultar");
        },
      });
    });
  }

  /**
   * Mostrar tracking admin
   */
  function displayTrackingAdmin($container, data, guia) {
    var html = '<div class="tracking-info">';
    html += '<div class="tracking-header">';
    html += "<h4>Guía: " + guia + "</h4>";
    if (data.estadoActual) {
      html += '<span class="tracking-status">' + data.estadoActual + "</span>";
    }
    html += "</div>";

    html += '<div class="tracking-body">';

    if (data.movimientos && data.movimientos.length > 0) {
      html += '<div class="tracking-timeline-admin">';
      data.movimientos.forEach(function (mov, i) {
        html +=
          '<div class="timeline-item' + (i === 0 ? "" : " completed") + '">';
        html += '<div class="timeline-date">' + (mov.fecha || "") + "</div>";
        html += '<div class="timeline-status">' + (mov.estado || "") + "</div>";
        html +=
          '<div class="timeline-location">' + (mov.ciudad || "") + "</div>";
        html += "</div>";
      });
      html += "</div>";
    } else {
      html += "<p>No hay movimientos registrados.</p>";
      html += "<pre>" + JSON.stringify(data, null, 2) + "</pre>";
    }

    html += "</div></div>";

    $container.addClass("success").html(html);
  }
})(jQuery);
