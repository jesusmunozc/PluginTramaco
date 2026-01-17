/**
 * Laar Courier API - Scripts Frontend
 * Star Brand
 */

(function ($) {
  "use strict";

  // Variables globales
  var ciudadesLoaded = false;
  var ciudadesData = [];

  /**
   * Inicialización
   */
  $(document).ready(function () {
    initTracking();
    initCotizacion();
    initGenerarGuia();
  });

  /**
   * Cargar ciudades
   */
  function loadCiudades(callback) {
    if (ciudadesLoaded) {
      if (callback) callback(ciudadesData);
      return;
    }

    $.ajax({
      url: laarApi.ajaxUrl,
      type: "POST",
      data: {
        action: "laar_ciudades",
      },
      success: function (response) {
        if (response.success && response.data) {
          ciudadesData = response.data;
          ciudadesLoaded = true;
          if (callback) callback(ciudadesData);
        }
      },
      error: function () {
        console.error("Error cargando ciudades");
      },
    });
  }

  /**
   * Poblar select de ciudades
   */
  function populateCiudadesSelect($select) {
    loadCiudades(function (ciudades) {
      $select.empty().append('<option value="">Seleccione...</option>');
      ciudades.forEach(function (ciudad) {
        $select.append(
          $("<option>", {
            value: ciudad.codigo,
            text: ciudad.nombre,
          })
        );
      });
    });
  }

  /**
   * Mostrar resultado
   */
  function showResult($container, type, content) {
    $container
      .removeClass("success error info")
      .addClass(type + " active")
      .html(content);
  }

  /**
   * TRACKING
   */
  function initTracking() {
    var $form = $("#laar-tracking-form");
    if (!$form.length) return;

    $form.on("submit", function (e) {
      e.preventDefault();

      var $btn = $form.find('button[type="submit"]');
      var $result = $("#tracking-result");
      var guia = $("#tracking-guia").val().trim();

      if (!guia) {
        showResult(
          $result,
          "error",
          "<p>Por favor ingrese el número de guía.</p>"
        );
        return;
      }

      $btn
        .prop("disabled", true)
        .addClass("laar-loading")
        .text("Consultando...");

      $.ajax({
        url: laarApi.ajaxUrl,
        type: "POST",
        data: {
          action: "laar_tracking",
          guia: guia,
        },
        success: function (response) {
          if (response.success && response.data) {
            displayTrackingResult($result, response.data);
          } else {
            showResult(
              $result,
              "error",
              "<p>No se encontró información para la guía: <strong>" +
                guia +
                "</strong></p>"
            );
          }
        },
        error: function () {
          showResult(
            $result,
            "error",
            "<p>Error de conexión. Intente nuevamente.</p>"
          );
        },
        complete: function () {
          $btn
            .prop("disabled", false)
            .removeClass("laar-loading")
            .text("Consultar");
        },
      });
    });
  }

  /**
   * Mostrar resultado de tracking
   */
  function displayTrackingResult($container, data) {
    var html = '<div class="tracking-info">';

    // Header con información principal
    html += '<div class="tracking-header-front">';
    html += "<h4>Guía: " + (data.guia || "N/A") + "</h4>";
    html +=
      '<p class="tracking-estado">' +
      (data.estadoActual || "En proceso") +
      "</p>";
    html += "</div>";

    // Timeline de movimientos
    if (data.movimientos && data.movimientos.length > 0) {
      html += '<div class="tracking-timeline">';
      data.movimientos.forEach(function (mov, index) {
        var isFirst = index === 0;
        html +=
          '<div class="tracking-item' +
          (isFirst ? " current" : " completed") +
          '">';
        html +=
          '<div class="tracking-item-date">' + (mov.fecha || "") + "</div>";
        html +=
          '<div class="tracking-item-status">' + (mov.estado || "") + "</div>";
        html +=
          '<div class="tracking-item-location">' +
          (mov.ciudad || "") +
          "</div>";
        html += "</div>";
      });
      html += "</div>";
    } else {
      html += '<p class="no-tracking">No hay movimientos registrados aún.</p>';
    }

    html += "</div>";

    showResult($container, "success", html);
  }

  /**
   * COTIZACIÓN
   */
  function initCotizacion() {
    var $form = $("#laar-cotizacion-form");
    if (!$form.length) return;

    // Cargar ciudades
    populateCiudadesSelect($("#cot-ciudad-origen"));
    populateCiudadesSelect($("#cot-ciudad-destino"));

    $form.on("submit", function (e) {
      e.preventDefault();

      var $btn = $form.find('button[type="submit"]');
      var $result = $("#cotizacion-result");

      var data = {
        action: "laar_cotizacion",
        ciudad_origen: $("#cot-ciudad-origen").val(),
        ciudad_destino: $("#cot-ciudad-destino").val(),
        servicio: $("#cot-servicio").val(),
        piezas: $("#cot-piezas").val(),
        peso: $("#cot-peso").val(),
      };

      if (!data.ciudad_origen || !data.ciudad_destino) {
        showResult(
          $result,
          "error",
          "<p>Por favor seleccione ciudad origen y destino.</p>"
        );
        return;
      }

      $btn
        .prop("disabled", true)
        .addClass("laar-loading")
        .text("Calculando...");

      $.ajax({
        url: laarApi.ajaxUrl,
        type: "POST",
        data: data,
        success: function (response) {
          if (response.success && response.data) {
            displayCotizacionResult($result, response.data, data);
          } else {
            showResult(
              $result,
              "error",
              "<p>No se pudo calcular la tarifa. Verifique los datos e intente nuevamente.</p>"
            );
          }
        },
        error: function () {
          showResult(
            $result,
            "error",
            "<p>Error de conexión. Intente nuevamente.</p>"
          );
        },
        complete: function () {
          $btn
            .prop("disabled", false)
            .removeClass("laar-loading")
            .text("Cotizar");
        },
      });
    });
  }

  /**
   * Mostrar resultado de cotización
   */
  function displayCotizacionResult($container, data, params) {
    var html = '<div class="cotizacion-resultado">';
    html += "<h4>Cotización de Envío</h4>";
    html +=
      '<div class="cotizacion-precio">$' +
      parseFloat(data.valorTotal || 0).toFixed(2) +
      "</div>";

    html += '<div class="cotizacion-detalle">';
    if (data.valorFlete) {
      html += '<div class="cotizacion-item">';
      html += '<div class="cotizacion-item-label">Flete</div>';
      html +=
        '<div class="cotizacion-item-value">$' +
        parseFloat(data.valorFlete).toFixed(2) +
        "</div>";
      html += "</div>";
    }
    if (data.valorSeguro) {
      html += '<div class="cotizacion-item">';
      html += '<div class="cotizacion-item-label">Seguro</div>';
      html +=
        '<div class="cotizacion-item-value">$' +
        parseFloat(data.valorSeguro).toFixed(2) +
        "</div>";
      html += "</div>";
    }
    html += '<div class="cotizacion-item">';
    html += '<div class="cotizacion-item-label">Piezas</div>';
    html += '<div class="cotizacion-item-value">' + params.piezas + "</div>";
    html += "</div>";
    html += '<div class="cotizacion-item">';
    html += '<div class="cotizacion-item-label">Peso</div>';
    html += '<div class="cotizacion-item-value">' + params.peso + " kg</div>";
    html += "</div>";
    html += "</div>";

    html += "</div>";

    showResult($container, "success", html);
  }

  /**
   * GENERAR GUÍA
   */
  function initGenerarGuia() {
    var $form = $("#laar-generar-guia-form");
    if (!$form.length) return;

    // Cargar ciudades
    populateCiudadesSelect($("#fg-ciudad"));

    $form.on("submit", function (e) {
      e.preventDefault();

      var $btn = $form.find('button[type="submit"]');
      var $result = $("#generar-guia-result");
      var formData = $form.serializeArray();

      var data = { action: "laar_generar_guia", nonce: laarApi.nonce };
      formData.forEach(function (item) {
        data[item.name] = item.value;
      });

      $btn.prop("disabled", true).addClass("laar-loading").text("Generando...");

      $.ajax({
        url: laarApi.ajaxUrl,
        type: "POST",
        data: data,
        success: function (response) {
          if (response.success && response.data && response.data.numeroGuia) {
            displayGuiaGenerada($result, response.data);
            $form[0].reset();
          } else {
            var msg =
              response.message ||
              "Error al generar la guía. Verifique los datos.";
            showResult($result, "error", "<p>" + msg + "</p>");
          }
        },
        error: function () {
          showResult(
            $result,
            "error",
            "<p>Error de conexión. Intente nuevamente.</p>"
          );
        },
        complete: function () {
          $btn
            .prop("disabled", false)
            .removeClass("laar-loading")
            .text("Generar Guía");
        },
      });
    });
  }

  /**
   * Mostrar guía generada
   */
  function displayGuiaGenerada($container, data) {
    var html = '<div class="guia-generada">';
    html += '<div class="laar-alert laar-alert-success">';
    html += "<strong>¡Guía generada exitosamente!</strong>";
    html += "</div>";
    html += '<div class="guia-numero">' + data.numeroGuia + "</div>";

    html += '<div class="guia-acciones">';
    html +=
      '<button type="button" class="btn btn-primary" onclick="window.print()">Imprimir</button>';
    html +=
      '<button type="button" class="btn btn-secondary" data-guia="' +
      data.numeroGuia +
      '" id="btn-descargar-pdf">Descargar PDF</button>';
    html += "</div>";

    html += "</div>";

    showResult($container, "success", html);

    // Handler para PDF
    $("#btn-descargar-pdf").on("click", function () {
      var guia = $(this).data("guia");
      // Implementar descarga de PDF
      alert("Descargando PDF para guía: " + guia);
    });
  }
})(jQuery);
