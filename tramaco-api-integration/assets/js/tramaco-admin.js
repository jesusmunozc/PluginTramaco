/**
 * Tramaco API Integration - Admin Scripts
 */

(function ($) {
  "use strict";

  var ubicaciones = null;

  $(document).ready(function () {
    initTestConnection();
    initAdminTrackingForm();
    initAdminGenerarGuiaForm();
    loadUbicacionesAdmin();
  });

  /**
   * Cargar ubicaciones para el admin
   */
  function loadUbicacionesAdmin() {
    if ($("#dest_provincia").length === 0) return;

    $.ajax({
      url: tramacoAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "tramaco_ubicaciones",
      },
      success: function (response) {
        if (response.success && response.data) {
          ubicaciones = response.data;
          populateProvinciasAdmin();
        }
      },
    });
  }

  /**
   * Poblar provincias en admin
   */
  function populateProvinciasAdmin() {
    var $select = $("#dest_provincia");
    $select.empty().append('<option value="">Seleccione...</option>');

    if (ubicaciones && ubicaciones.provincias) {
      $.each(ubicaciones.provincias, function (i, provincia) {
        $select.append(
          '<option value="' +
            provincia.id +
            '">' +
            provincia.nombre +
            "</option>"
        );
      });
    }

    // Eventos de cambio
    $select.on("change", function () {
      var provinciaId = $(this).val();
      populateCantonesAdmin(provinciaId);
    });

    $("#dest_canton").on("change", function () {
      var provinciaId = $("#dest_provincia").val();
      var cantonId = $(this).val();
      populateParroquiasAdmin(provinciaId, cantonId);
    });
  }

  /**
   * Poblar cantones en admin
   */
  function populateCantonesAdmin(provinciaId) {
    var $select = $("#dest_canton");
    $select.empty().append('<option value="">Seleccione...</option>');
    $("#dest_parroquia")
      .empty()
      .append('<option value="">Seleccione cantón primero...</option>');

    if (!provinciaId || !ubicaciones) return;

    $.each(ubicaciones.provincias, function (i, provincia) {
      if (provincia.id == provinciaId && provincia.cantones) {
        $.each(provincia.cantones, function (j, canton) {
          $select.append(
            '<option value="' + canton.id + '">' + canton.nombre + "</option>"
          );
        });
      }
    });
  }

  /**
   * Poblar parroquias en admin
   */
  function populateParroquiasAdmin(provinciaId, cantonId) {
    var $select = $("#dest_parroquia");
    $select.empty().append('<option value="">Seleccione...</option>');

    if (!provinciaId || !cantonId || !ubicaciones) return;

    $.each(ubicaciones.provincias, function (i, provincia) {
      if (provincia.id == provinciaId && provincia.cantones) {
        $.each(provincia.cantones, function (j, canton) {
          if (canton.id == cantonId && canton.parroquias) {
            $.each(canton.parroquias, function (k, parroquia) {
              $select.append(
                '<option value="' +
                  parroquia.id +
                  '">' +
                  parroquia.nombre +
                  "</option>"
              );
            });
          }
        });
      }
    });
  }

  /**
   * Test de conexión
   */
  function initTestConnection() {
    $("#test-connection").on("click", function () {
      var $button = $(this);
      var $result = $("#connection-result");

      $button.prop("disabled", true).text("Probando...");
      $result.removeClass("success error").html("");

      $.ajax({
        url: tramacoAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "tramaco_auth",
          nonce: tramacoAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            $result
              .addClass("success")
              .html(
                "<strong>✅ Conexión exitosa!</strong><br>Token: " +
                  (response.token
                    ? response.token.substring(0, 50) + "..."
                    : "Generado")
              );
          } else {
            $result
              .addClass("error")
              .html(
                "<strong>❌ Error de conexión</strong><br>" +
                  (response.message || "Error desconocido")
              );
          }
        },
        error: function (xhr, status, error) {
          $result
            .addClass("error")
            .html("<strong>❌ Error de conexión</strong><br>" + error);
        },
        complete: function () {
          $button.prop("disabled", false).text("Probar Autenticación");
        },
      });
    });
  }

  /**
   * Formulario de tracking en admin
   */
  function initAdminTrackingForm() {
    $("#form-tracking-admin").on("submit", function (e) {
      e.preventDefault();

      var $form = $(this);
      var $result = $("#tracking-result");
      var $button = $form.find('button[type="submit"]');
      var guia = $("#numero_guia").val();

      if (!guia) {
        showAdminResult(
          $result,
          "error",
          "Por favor ingrese un número de guía"
        );
        return;
      }

      $button
        .prop("disabled", true)
        .html('<span class="spinner-tramaco"></span> Consultando...');
      $result.removeClass("success error").html("");

      $.ajax({
        url: tramacoAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "tramaco_tracking",
          guia: guia,
        },
        success: function (response) {
          if (response.success && response.data) {
            displayAdminTrackingResult($result, response.data);
          } else {
            var message =
              response.data && response.data.excepcion
                ? response.data.excepcion
                : "No se encontró información";
            showAdminResult($result, "error", message);
          }
        },
        error: function () {
          showAdminResult($result, "error", "Error al consultar tracking");
        },
        complete: function () {
          $button.prop("disabled", false).text("Consultar");
        },
      });
    });
  }

  /**
   * Mostrar resultado de tracking en admin
   */
  function displayAdminTrackingResult($container, data) {
    var html = "";

    if (data.lstSalidaTrackGuiaWs && data.lstSalidaTrackGuiaWs.length > 0) {
      var transaccion = data.lstSalidaTrackGuiaWs[0].transaccion;

      if (transaccion) {
        html += '<div class="tramaco-info-card">';
        html += "<h4>Información del Envío</h4>";
        html += '<div class="tramaco-info-grid">';
        html +=
          '<div class="tramaco-info-item"><label>Guía</label><span>' +
          (transaccion.guia || "-") +
          "</span></div>";
        html +=
          '<div class="tramaco-info-item"><label>Estado</label><span>' +
          (transaccion.estado || "-") +
          "</span></div>";
        html +=
          '<div class="tramaco-info-item"><label>Origen</label><span>' +
          (transaccion.origen || "-") +
          "</span></div>";
        html +=
          '<div class="tramaco-info-item"><label>Destino</label><span>' +
          (transaccion.destino || "-") +
          "</span></div>";
        html +=
          '<div class="tramaco-info-item"><label>Cliente</label><span>' +
          (transaccion.cliente || "-") +
          "</span></div>";
        html +=
          '<div class="tramaco-info-item"><label>Contrato</label><span>' +
          (transaccion.contrato || "-") +
          "</span></div>";
        html += "</div>";
        html += "</div>";
      }

      html += '<div class="tramaco-info-card">';
      html += "<h4>Historial de Tracking</h4>";
      html += '<div class="admin-tracking-timeline">';

      $.each(data.lstSalidaTrackGuiaWs, function (i, item) {
        html += '<div class="admin-tracking-item">';
        html += '<div class="status">' + (item.estado || "-") + "</div>";
        html += '<div class="date">' + (item.fechaHora || "-") + "</div>";
        if (item.descripcion) {
          html += '<div class="description">' + item.descripcion + "</div>";
        }
        if (item.ciudad) {
          html += '<div class="description">📍 ' + item.ciudad + "</div>";
        }
        html += "</div>";
      });

      html += "</div>";
      html += "</div>";
    } else {
      html =
        '<div class="tramaco-alert warning">No se encontraron eventos para esta guía.</div>';
    }

    $container.addClass("success").html(html);
  }

  /**
   * Formulario de generar guía en admin
   */
  function initAdminGenerarGuiaForm() {
    $("#form-generar-guia").on("submit", function (e) {
      e.preventDefault();

      var $form = $(this);
      var $result = $("#guia-result");
      var $button = $form.find('button[type="submit"]');

      // Validar campos requeridos
      var required = [
        "dest_nombres",
        "dest_apellidos",
        "dest_ci_ruc",
        "dest_telefono",
        "dest_parroquia",
        "dest_calle_primaria",
        "carga_descripcion",
        "carga_peso",
      ];

      var isValid = true;
      $.each(required, function (i, field) {
        var $field = $("#" + field);
        if (!$field.val()) {
          $field.css("border-color", "red");
          isValid = false;
        } else {
          $field.css("border-color", "");
        }
      });

      if (!isValid) {
        showAdminResult(
          $result,
          "error",
          "Por favor complete todos los campos obligatorios"
        );
        return;
      }

      $button
        .prop("disabled", true)
        .html('<span class="spinner-tramaco"></span> Generando...');
      $result.removeClass("success error").html("");

      var formData = $form.serialize();
      formData += "&action=tramaco_generar_guia&nonce=" + tramacoAdmin.nonce;

      $.ajax({
        url: tramacoAdmin.ajaxUrl,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success && response.data && response.data.lstGuias) {
            displayAdminGuiaGenerada($result, response.data);
            $form[0].reset();
          } else {
            var message =
              response.data && response.data.excepcion
                ? response.data.excepcion
                : "No se pudo generar la guía";
            showAdminResult($result, "error", message);
          }
        },
        error: function () {
          showAdminResult($result, "error", "Error al generar guía");
        },
        complete: function () {
          $button.prop("disabled", false).text("Generar Guía");
        },
      });
    });
  }

  /**
   * Mostrar guía generada en admin
   */
  function displayAdminGuiaGenerada($container, data) {
    var html = '<div class="guia-generada-admin">';
    html += "<h3>✅ ¡Guía Generada Exitosamente!</h3>";

    if (data.lstGuias && data.lstGuias.length > 0) {
      $.each(data.lstGuias, function (i, guia) {
        html += '<div class="numero">' + guia.guia + "</div>";
      });

      var guiasStr = data.lstGuias
        .map(function (g) {
          return g.guia;
        })
        .join(",");

      html += '<div class="acciones">';
      html +=
        '<button type="button" class="button button-primary btn-descargar-pdf" data-guias="' +
        guiasStr +
        '">';
      html += "📄 Descargar PDF</button>";
      html +=
        '<button type="button" class="button btn-tracking" data-guia="' +
        data.lstGuias[0].guia +
        '">';
      html += "🔍 Ver Tracking</button>";
      html += "</div>";
    }

    html += "</div>";
    $container.addClass("success").html(html);

    // Evento para descargar PDF
    $container.find(".btn-descargar-pdf").on("click", function () {
      var guias = $(this).data("guias").split(",");
      downloadPdfAdmin(guias);
    });

    // Evento para ver tracking
    $container.find(".btn-tracking").on("click", function () {
      var guia = $(this).data("guia");
      window.location.href = "admin.php?page=tramaco-api-tracking&guia=" + guia;
    });
  }

  /**
   * Descargar PDF en admin
   */
  function downloadPdfAdmin(guias) {
    $.ajax({
      url: tramacoAdmin.ajaxUrl,
      type: "POST",
      data: {
        action: "tramaco_generar_pdf",
        nonce: tramacoAdmin.nonce,
        guias: guias,
      },
      success: function (response) {
        if (response.success && response.data && response.data.inStrPfd) {
          var byteCharacters = atob(response.data.inStrPfd);
          var byteNumbers = new Array(byteCharacters.length);
          for (var i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
          }
          var byteArray = new Uint8Array(byteNumbers);
          var blob = new Blob([byteArray], { type: "application/pdf" });
          var url = window.URL.createObjectURL(blob);
          var a = document.createElement("a");
          a.href = url;
          a.download = "guia-" + guias[0] + ".pdf";
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(url);
        } else {
          alert("No se pudo generar el PDF");
        }
      },
      error: function () {
        alert("Error al generar PDF");
      },
    });
  }

  /**
   * Mostrar resultado en admin
   */
  function showAdminResult($container, type, message) {
    var icon = type === "error" ? "❌" : "✅";
    $container
      .removeClass("success error")
      .addClass(type)
      .html(
        '<div class="tramaco-alert ' +
          type +
          '">' +
          icon +
          " " +
          message +
          "</div>"
      );
  }
})(jQuery);
