/**
 * Tramaco API Integration - Frontend Scripts
 */

(function ($) {
  "use strict";

  // Variables globales
  var ubicaciones = null;

  /**
   * Inicialización
   */
  $(document).ready(function () {
    initTrackingForm();
    initCotizacionForm();
    initGenerarGuiaForm();
  });

  /**
   * Cargar ubicaciones geográficas
   */
  function loadUbicaciones(callback) {
    if (ubicaciones) {
      callback(ubicaciones);
      return;
    }

    $.ajax({
      url: tramacoApi.ajaxUrl,
      type: "POST",
      data: {
        action: "tramaco_ubicaciones",
      },
      success: function (response) {
        if (response.success && response.data) {
          ubicaciones = response.data;
          callback(ubicaciones);
        }
      },
      error: function () {
        console.error("Error al cargar ubicaciones");
      },
    });
  }

  /**
   * Poblar select de provincias
   */
  function populateProvincias(selectId) {
    loadUbicaciones(function (data) {
      var $select = $(selectId);
      $select.empty().append('<option value="">Seleccione...</option>');

      if (data.provincias) {
        $.each(data.provincias, function (i, provincia) {
          $select.append(
            '<option value="' +
              provincia.id +
              '">' +
              provincia.nombre +
              "</option>"
          );
        });
      }

      // Refrescar select personalizado si existe
      if ($select.data("tramaco-custom-select")) {
        $select.data("tramaco-custom-select").refresh();
      }
    });
  }

  /**
   * Poblar select de cantones
   */
  function populateCantones(provinciaId, selectId) {
    var $select = $(selectId);
    $select.empty().append('<option value="">Seleccione...</option>');

    if (!provinciaId || !ubicaciones) {
      // Refrescar select personalizado si existe
      if ($select.data("tramaco-custom-select")) {
        $select.data("tramaco-custom-select").refresh();
      }
      return;
    }

    $.each(ubicaciones.provincias, function (i, provincia) {
      if (provincia.id == provinciaId && provincia.cantones) {
        $.each(provincia.cantones, function (j, canton) {
          $select.append(
            '<option value="' + canton.id + '">' + canton.nombre + "</option>"
          );
        });
      }
    });

    // Refrescar select personalizado si existe
    if ($select.data("tramaco-custom-select")) {
      $select.data("tramaco-custom-select").refresh();
    }
  }

  /**
   * Poblar select de parroquias
   */
  function populateParroquias(provinciaId, cantonId, selectId) {
    var $select = $(selectId);
    $select.empty().append('<option value="">Seleccione...</option>');

    if (!provinciaId || !cantonId || !ubicaciones) {
      // Refrescar select personalizado si existe
      if ($select.data("tramaco-custom-select")) {
        $select.data("tramaco-custom-select").refresh();
      }
      return;
    }

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

    // Refrescar select personalizado si existe
    if ($select.data("tramaco-custom-select")) {
      $select.data("tramaco-custom-select").refresh();
    }
  }

  /**
   * Inicializar formulario de tracking
   */
  function initTrackingForm() {
    // Inicializar select personalizado para el tipo de verificación
    setTimeout(function () {
      $("#tracking-verificacion-tipo").tramacoCustomSelect();
    }, 100);

    // Cambiar label, icono y placeholder según tipo de verificación
    $("#tracking-verificacion-tipo").on("change", function () {
      var tipo = $(this).val();
      var $labelIcon = $(".label-icon-dynamic");
      var $labelText = $(".label-text-dynamic");
      var $input = $("#tracking-verificacion-valor");
      var $help = $(".verificacion-help");

      // Determinar si es teléfono o documento
      var esTelefono = tipo.includes("telefono");
      var esDestinatario = tipo.includes("destinatario");

      // Actualizar icono
      $labelIcon.text(esTelefono ? "📱" : "🆔");

      // Actualizar texto del label
      var persona = esDestinatario ? "Destinatario" : "Remitente";
      var tipoDato = esTelefono ? "Teléfono" : "Cédula/RUC";
      $labelText.text(tipoDato + " del " + persona);

      // Actualizar placeholder y ayuda
      if (esTelefono) {
        $input.attr("placeholder", "Ej: 0987654321");
        $input.attr("maxlength", "13");
        $help.text("Ingresa el teléfono completo (10 dígitos)");
      } else {
        $input.attr("placeholder", "Ej: 1234567890001");
        $input.attr("maxlength", "13");
        $help.text("Ingresa la cédula (10 dígitos) o RUC (13 dígitos)");
      }

      // Limpiar el input al cambiar tipo
      $input.val("");
    });

    $("#tramaco-tracking-form").on("submit", function (e) {
      e.preventDefault();

      var $form = $(this);
      var $result = $("#tracking-result");
      var $button = $form.find('button[type="submit"]');
      var guia = $("#tracking-guia").val().trim();
      var verificacionTipo = $("#tracking-verificacion-tipo").val();
      var verificacionValor = $("#tracking-verificacion-valor").val().trim();

      if (!guia) {
        showResult($result, "error", "Por favor ingrese un número de guía");
        return;
      }

      if (!verificacionValor) {
        showResult(
          $result,
          "error",
          "Por favor ingrese el dato de verificación"
        );
        return;
      }

      // Validación básica de formato
      var soloNumeros = verificacionValor.replace(/[^0-9]/g, "");
      if (verificacionTipo.includes("telefono")) {
        if (soloNumeros.length !== 10) {
          showResult($result, "error", "El teléfono debe tener 10 dígitos");
          return;
        }
      } else {
        if (soloNumeros.length !== 10 && soloNumeros.length !== 13) {
          showResult(
            $result,
            "error",
            "La cédula debe tener 10 dígitos o el RUC 13 dígitos"
          );
          return;
        }
      }

      $button
        .prop("disabled", true)
        .html('<span class="btn-spinner"></span> Verificando y consultando...');
      $result
        .removeClass("show success error info error-verification")
        .html("");

      $.ajax({
        url: tramacoApi.ajaxUrl,
        type: "POST",
        data: {
          action: "tramaco_tracking",
          guia: guia,
          verificacion_tipo: verificacionTipo,
          verificacion_valor: verificacionValor,
        },
        success: function (response) {
          if (response.success && response.data) {
            displayTrackingResult($result, response.data);
          } else {
            var message =
              response.message || "No se encontró información para esta guía";
            var errorClass =
              response.error_type === "verification_failed"
                ? "error-verification"
                : "error";
            showResult($result, errorClass, message);
          }
        },
        error: function () {
          showResult(
            $result,
            "error",
            "Error al consultar el tracking. Por favor intente nuevamente."
          );
        },
        complete: function () {
          $button
            .prop("disabled", false)
            .html('<span class="btn-icon">🔍</span> Consultar Seguimiento');
        },
      });
    });
  }

  /**
   * Mostrar resultado del tracking - VERSIÓN PROFESIONAL
   */
  function displayTrackingResult($container, data) {
    var html = "";
    var transaccion = data.transaccion;
    var destinatario = data.destinatario;
    var remitente = data.remitente;
    var eventos = data.lstSalidaTrackGuiaWs || [];

    if (!transaccion || eventos.length === 0) {
      html =
        '<div class="tracking-no-data"><p>No se encontraron datos para esta guía.</p></div>';
      $container.html(html).addClass("show info");
      return;
    }

    // Header con información principal
    html += '<div class="tracking-result-container">';

    // Estado principal y número de guía
    html += '<div class="tracking-header-card">';
    html +=
      '<div class="tracking-status-badge status-' +
      (transaccion.estado || "unknown").toLowerCase().replace(/\s+/g, "-") +
      '">';
    html += '<span class="status-icon">📦</span>';
    html += '<div class="status-content">';
    html += '<span class="status-label">Estado Actual</span>';
    html +=
      '<span class="status-value">' +
      (transaccion.estado || "Sin estado") +
      "</span>";
    html += "</div>";
    html += "</div>";
    html += '<div class="tracking-guia-number">';
    html += '<span class="guia-label">Guía Nº</span>';
    html += '<span class="guia-value">' + (transaccion.guia || "-") + "</span>";
    html += "</div>";
    html += "</div>";

    // Barra de progreso visual
    html += '<div class="tracking-progress-bar">';
    var estados = [
      "ADMISION",
      "EN TRANSITO",
      "EN DESTINO",
      "EN ENTREGA",
      "ENTREGADO",
    ];
    var estadoActual = (transaccion.estado || "").toUpperCase();
    var progreso = estados.indexOf(estadoActual);
    if (progreso === -1) progreso = 0;
    var porcentaje = ((progreso + 1) / estados.length) * 100;

    html += '<div class="progress-steps">';
    estados.forEach(function (estado, index) {
      var activo = index <= progreso ? "active" : "";
      var completado = index < progreso ? "completed" : "";
      var actual = index === progreso ? "current" : "";
      html +=
        '<div class="progress-step ' +
        activo +
        " " +
        completado +
        " " +
        actual +
        '">';
      html += '<div class="step-marker"></div>';
      html += '<div class="step-label">' + estado + "</div>";
      html += "</div>";
    });
    html += "</div>";
    html +=
      '<div class="progress-bar-bg"><div class="progress-bar-fill" style="width: ' +
      porcentaje +
      '%"></div></div>';
    html += "</div>";

    // Grid de información detallada
    html += '<div class="tracking-info-grid">';

    // Columna 1: Información del Envío
    html += '<div class="info-card">';
    html +=
      '<h4 class="info-card-title"><span class="icon">📋</span> Información del Envío</h4>';
    html += '<div class="info-rows">';
    html +=
      '<div class="info-row"><span class="info-label">Origen:</span><span class="info-value">' +
      (transaccion.origen || "-") +
      " (" +
      (transaccion.parOrigen || "-") +
      ")</span></div>";
    html +=
      '<div class="info-row"><span class="info-label">Destino:</span><span class="info-value">' +
      (transaccion.destino || "-") +
      " (" +
      (transaccion.parDestino || "-") +
      ")</span></div>";
    html +=
      '<div class="info-row"><span class="info-label">Producto:</span><span class="info-value">' +
      (transaccion.producto || "-") +
      "</span></div>";
    html +=
      '<div class="info-row"><span class="info-label">Paquetes:</span><span class="info-value">' +
      (transaccion.paquetes || "1") +
      "</span></div>";
    html +=
      '<div class="info-row"><span class="info-label">Peso:</span><span class="info-value">' +
      (transaccion.pesoCliente || transaccion.pesoReal || "-") +
      " kg</span></div>";

    if (transaccion.valorAsegurado > 0) {
      html +=
        '<div class="info-row"><span class="info-label">Valor Asegurado:</span><span class="info-value">$' +
        parseFloat(transaccion.valorAsegurado).toFixed(2) +
        "</span></div>";
    }
    html += "</div>";
    html += "</div>";

    // Columna 2: Destinatario
    html += '<div class="info-card">';
    html +=
      '<h4 class="info-card-title"><span class="icon">👤</span> Destinatario</h4>';
    html += '<div class="info-rows">';
    if (destinatario) {
      var nombreCompleto =
        (destinatario.nombres || "") + " " + (destinatario.apellidos || "");
      html +=
        '<div class="info-row"><span class="info-label">Nombre:</span><span class="info-value">' +
        nombreCompleto.trim() +
        "</span></div>";
      html +=
        '<div class="info-row"><span class="info-label">Teléfono:</span><span class="info-value">' +
        (destinatario.telefono || "-") +
        "</span></div>";
      html +=
        '<div class="info-row"><span class="info-label">Dirección:</span><span class="info-value">' +
        (destinatario.callePrimaria || "-") +
        "</span></div>";

      if (destinatario.referencia) {
        html +=
          '<div class="info-row"><span class="info-label">Referencia:</span><span class="info-value">' +
          destinatario.referencia +
          "</span></div>";
      }

      if (transaccion.quienRecibe) {
        html +=
          '<div class="info-row highlighted"><span class="info-label">Recibió:</span><span class="info-value">✅ ' +
          transaccion.quienRecibe +
          "</span></div>";
      }
    }
    html += "</div>";
    html += "</div>";

    html += "</div>";

    // Descripción del contenido
    if (transaccion.descripcion) {
      html += '<div class="tracking-description-card">';
      html += '<h4><span class="icon">📦</span> Descripción del Contenido</h4>';
      html += "<p>" + transaccion.descripcion + "</p>";
      html += "</div>";
    }

    // Timeline de eventos - PROFESIONAL
    html += '<div class="tracking-timeline-section">';
    html +=
      '<h4 class="timeline-title"><span class="icon">📍</span> Historial de Seguimiento</h4>';
    html += '<div class="tracking-timeline">';

    eventos.forEach(function (evento, index) {
      var isFirst = index === 0;
      var itemClass = isFirst ? "timeline-item current" : "timeline-item";

      html += '<div class="' + itemClass + '">';
      html += '<div class="timeline-marker"></div>';
      html += '<div class="timeline-content">';
      html += '<div class="timeline-header">';
      html +=
        '<span class="timeline-status">' + (evento.estado || "-") + "</span>";
      html +=
        '<span class="timeline-date">' + (evento.fechaHora || "-") + "</span>";
      html += "</div>";

      if (evento.descripcion) {
        html +=
          '<div class="timeline-description">' + evento.descripcion + "</div>";
      }

      var detalles = [];
      if (evento.ciudad) detalles.push("📍 " + evento.ciudad);
      if (evento.transporte) detalles.push("🚚 " + evento.transporte);
      if (evento.usuario) detalles.push("👤 " + evento.usuario);

      if (detalles.length > 0) {
        html +=
          '<div class="timeline-details">' + detalles.join(" • ") + "</div>";
      }

      html += "</div>";
      html += "</div>";
    });

    html += "</div>";
    html += "</div>";

    // Footer con información adicional
    html += '<div class="tracking-footer">';
    html += '<div class="footer-info">';
    html +=
      '<span class="footer-label">Cliente:</span> ' +
      (transaccion.cliente || "-");
    html += "</div>";
    html += '<div class="footer-info">';
    html +=
      '<span class="footer-label">Contrato:</span> ' +
      (transaccion.contrato || "-");
    html += "</div>";
    html += "</div>";

    html += "</div>"; // Cierre tracking-result-container

    $container.html(html).addClass("show success");
  }

  /**
   * Inicializar formulario de cotización
   */
  function initCotizacionForm() {
    var $provinciaSelect = $("#cotizacion-provincia");
    var $cantonSelect = $("#cotizacion-canton");
    var $parroquiaSelect = $("#cotizacion-parroquia");

    if ($provinciaSelect.length === 0) return;

    // Cargar provincias
    populateProvincias("#cotizacion-provincia");

    // Evento cambio de provincia
    $provinciaSelect.on("change", function () {
      var provinciaId = $(this).val();
      populateCantones(provinciaId, "#cotizacion-canton");
      $parroquiaSelect
        .empty()
        .append('<option value="">Seleccione cantón primero...</option>');
    });

    // Evento cambio de cantón
    $cantonSelect.on("change", function () {
      var provinciaId = $provinciaSelect.val();
      var cantonId = $(this).val();
      populateParroquias(provinciaId, cantonId, "#cotizacion-parroquia");
    });

    // Submit del formulario
    $("#tramaco-cotizacion-form").on("submit", function (e) {
      e.preventDefault();

      var $form = $(this);
      var $result = $("#cotizacion-result");
      var $button = $form.find('button[type="submit"]');

      var parroquia = $parroquiaSelect.val();
      var peso = $("#cotizacion-peso").val();

      if (!parroquia || !peso) {
        showResult($result, "error", "Por favor complete todos los campos");
        return;
      }

      $button.prop("disabled", true).text("Calculando...");
      $result.removeClass("show success error info");

      $.ajax({
        url: tramacoApi.ajaxUrl,
        type: "POST",
        data: {
          action: "tramaco_cotizacion",
          parroquia_destino: parroquia,
          peso: peso,
          bultos: $("#cotizacion-bultos").val() || 1,
        },
        success: function (response) {
          if (response.success && response.data) {
            displayCotizacionResult($result, response.data);
          } else {
            var message =
              response.message ||
              (response.data && response.data.excepcion
                ? response.data.excepcion
                : "No se pudo calcular la cotización");
            showResult($result, "error", message);
          }
        },
        error: function () {
          showResult(
            $result,
            "error",
            "Error al calcular cotización. Por favor intente nuevamente."
          );
        },
        complete: function () {
          $button
            .prop("disabled", false)
            .html('<span class="btn-icon">💰</span> Calcular Precio');
        },
      });
    });
  }

  /**
   * Mostrar resultado de cotización
   */
  function displayCotizacionResult($container, data) {
    // Manejar todas las estructuras de respuesta posibles
    var lstGuias =
      data.lstGuias ||
      (data.salidaCalcularPrecioGuiaWs &&
        data.salidaCalcularPrecioGuiaWs.lstGuias) ||
      (data.cuerpoRespuesta && data.cuerpoRespuesta.lstGuias) ||
      (data.cuerpoRespuesta &&
        data.cuerpoRespuesta.salidaCalcularPrecioGuiaWs &&
        data.cuerpoRespuesta.salidaCalcularPrecioGuiaWs.lstGuias) ||
      null;

    if (!lstGuias || lstGuias.length === 0) {
      showResult(
        $container,
        "error",
        "No se encontraron resultados de cotización"
      );
      return;
    }

    var guia = lstGuias[0];
    var subtotal = parseFloat(guia.subTotal || 0);
    var iva = parseFloat(guia.iva || 0);
    var seguro = parseFloat(guia.seguro || 0);
    var total = parseFloat(guia.total || 0);

    var html = '<div class="cotizacion-result-card">';
    html += '  <div class="cotizacion-header">';
    html += '    <span class="cotizacion-icon">💰</span>';
    html += '    <h3 class="cotizacion-title">Resultado de la Cotización</h3>';
    html += "  </div>";
    html += '  <div class="cotizacion-price">';
    html += '    <div class="price-label">Costo Total del Envío</div>';
    html += '    <div class="price-value">$' + total.toFixed(2) + "</div>";
    html += "  </div>";
    html += '  <div class="price-details">';
    html += '    <div class="price-item">';
    html += '      <span class="price-item-label">Subtotal</span>';
    html +=
      '      <span class="price-item-value">$' +
      subtotal.toFixed(2) +
      "</span>";
    html += "    </div>";
    html += '    <div class="price-item">';
    html += '      <span class="price-item-label">IVA (15%)</span>';
    html +=
      '      <span class="price-item-value">$' + iva.toFixed(2) + "</span>";
    html += "    </div>";
    if (seguro > 0) {
      html += '    <div class="price-item">';
      html += '      <span class="price-item-label">Seguro</span>';
      html +=
        '      <span class="price-item-value">$' +
        seguro.toFixed(2) +
        "</span>";
      html += "    </div>";
    }
    html += '    <div class="price-item price-total">';
    html += '      <span class="price-item-label">Total</span>';
    html +=
      '      <span class="price-item-value">$' + total.toFixed(2) + "</span>";
    html += "    </div>";
    html += "  </div>";

    if (guia.pesoVolumen && parseFloat(guia.pesoVolumen) > 0) {
      html += '  <div class="cotizacion-info">';
      html += '    <div class="info-icon">ℹ️</div>';
      html += '    <div class="info-text">';
      html +=
        "      <strong>Peso Volumétrico:</strong> " +
        parseFloat(guia.pesoVolumen).toFixed(2) +
        " kg";
      html += "    </div>";
      html += "  </div>";
    }

    html += "</div>";

    $container.html(html).addClass("show success");
  }

  /**
   * Inicializar formulario de generar guía
   */
  function initGenerarGuiaForm() {
    var $form = $("#tramaco-generar-guia-form");

    if ($form.length === 0) return;

    var $provinciaSelect = $("#fg-provincia");
    var $cantonSelect = $("#fg-canton");
    var $parroquiaSelect = $("#fg-parroquia");

    // Cargar provincias
    populateProvincias("#fg-provincia");

    // Evento cambio de provincia
    $provinciaSelect.on("change", function () {
      var provinciaId = $(this).val();
      populateCantones(provinciaId, "#fg-canton");
      $parroquiaSelect
        .empty()
        .append('<option value="">Seleccione cantón primero...</option>');
    });

    // Evento cambio de cantón
    $cantonSelect.on("change", function () {
      var provinciaId = $provinciaSelect.val();
      var cantonId = $(this).val();
      populateParroquias(provinciaId, cantonId, "#fg-parroquia");
    });

    // Submit del formulario
    $form.on("submit", function (e) {
      e.preventDefault();

      var $result = $("#generar-guia-result");
      var $button = $form.find('button[type="submit"]');

      $button.prop("disabled", true).text("Generando...");
      $result.removeClass("show success error");

      var formData = $form.serialize();
      formData += "&action=tramaco_generar_guia&nonce=" + tramacoApi.nonce;

      $.ajax({
        url: tramacoApi.ajaxUrl,
        type: "POST",
        data: formData,
        success: function (response) {
          if (response.success && response.data && response.data.lstGuias) {
            displayGuiaGenerada($result, response.data);
            $form[0].reset();
          } else {
            var message =
              response.data && response.data.excepcion
                ? response.data.excepcion
                : "No se pudo generar la guía";
            showResult($result, "error", message);
          }
        },
        error: function () {
          showResult(
            $result,
            "error",
            "Error al generar guía. Por favor intente nuevamente."
          );
        },
        complete: function () {
          $button.prop("disabled", false).text("Generar Guía");
        },
      });
    });
  }

  /**
   * Mostrar guía generada
   */
  function displayGuiaGenerada($container, data) {
    var html = '<div class="guia-generada">';
    html += "<h4>✅ ¡Guía Generada Exitosamente!</h4>";

    if (data.lstGuias && data.lstGuias.length > 0) {
      $.each(data.lstGuias, function (i, guia) {
        html += '<div class="numero-guia">' + guia.guia + "</div>";
      });

      html +=
        '<button type="button" class="btn btn-secondary btn-descargar-pdf" data-guias="' +
        JSON.stringify(
          data.lstGuias.map(function (g) {
            return g.guia;
          })
        ) +
        '">';
      html += "📄 Descargar PDF</button>";
    }

    html += "</div>";
    $container.html(html).addClass("show success");

    // Evento para descargar PDF
    $container.find(".btn-descargar-pdf").on("click", function () {
      var guias = $(this).data("guias");
      downloadPdf(guias);
    });
  }

  /**
   * Descargar PDF de guía
   */
  function downloadPdf(guias) {
    $.ajax({
      url: tramacoApi.ajaxUrl,
      type: "POST",
      data: {
        action: "tramaco_generar_pdf",
        nonce: tramacoApi.nonce,
        guias: guias,
      },
      success: function (response) {
        if (response.success && response.data && response.data.inStrPfd) {
          // Decodificar base64 y descargar
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
   * Mostrar resultado genérico
   */
  function showResult($container, type, message) {
    $container
      .removeClass("success error info")
      .addClass("show " + type)
      .html("<p>" + message + "</p>");
  }
})(jQuery);
