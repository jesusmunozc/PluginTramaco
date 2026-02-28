/**
 * Scripts para el checkout de WooCommerce con Tramaco
 *
 * Maneja:
 * - Selects dinámicos de ubicación (provincia/cantón/parroquia)
 * - Actualización de costos de envío en tiempo real
 * - Selector de ubicación en el carrito (Checkout en 2 pasos)
 *
 * @package Tramaco_API_Integration
 * @since 1.1.0
 */

(function ($) {
  "use strict";

  // ============================================================
  // UTILIDADES DE PERSISTENCIA CON LOCALSTORAGE
  // ============================================================
  var TramacoStorage = {
    keyPrefix: "tramaco_cart_",

    set: function (key, value) {
      try {
        localStorage.setItem(this.keyPrefix + key, JSON.stringify(value));
      } catch (e) {
        console.warn("Tramaco: No se pudo guardar en localStorage", e);
      }
    },

    get: function (key, defaultValue) {
      try {
        var item = localStorage.getItem(this.keyPrefix + key);
        return item ? JSON.parse(item) : defaultValue;
      } catch (e) {
        return defaultValue;
      }
    },

    remove: function (key) {
      try {
        localStorage.removeItem(this.keyPrefix + key);
      } catch (e) {}
    },

    // Guardar estado completo del envío
    saveShippingState: function (provincia, canton, parroquia, shippingCost) {
      this.set("shipping_state", {
        provincia: provincia,
        canton: canton,
        parroquia: parroquia,
        shippingCost: shippingCost,
        applied: !!(provincia && canton && parroquia && shippingCost),
        justCalculated: true, // Flag: acaba de calcularse, aún no se ha refrescado la página
        timestamp: Date.now(),
      });
    },

    // Marcar que la página ya se refrescó (eliminar flag justCalculated)
    markPageRefreshed: function () {
      var state = this.getShippingState();
      if (state && state.justCalculated) {
        state.justCalculated = false;
        this.set("shipping_state", state);
      }
    },

    // Verificar si acaba de calcularse (sin refresh)
    isJustCalculated: function () {
      var state = this.getShippingState();
      return state && state.justCalculated === true;
    },

    // Obtener estado del envío
    getShippingState: function () {
      var state = this.get("shipping_state", null);
      // Expirar después de 24 horas
      if (
        state &&
        state.timestamp &&
        Date.now() - state.timestamp > 24 * 60 * 60 * 1000
      ) {
        this.remove("shipping_state");
        return null;
      }
      return state;
    },

    // Limpiar estado cuando cambia ubicación
    clearShippingCost: function () {
      var state = this.getShippingState();
      if (state) {
        state.shippingCost = null;
        state.applied = false;
        this.set("shipping_state", state);
      }
    },
  };

  // Variable para trackear si el envío fue calculado
  var tramacoShippingApplied = false;

  // Flag para saber si ACABA de calcularse en esta misma carga (sin refresh)
  var tramacoJustCalculated = false;

  // Inicializar desde localStorage primero, luego PHP
  var savedState = TramacoStorage.getShippingState();
  if (savedState && savedState.applied) {
    if (savedState.justCalculated) {
      // Acaba de calcularse, ahora estamos en el refresh real → mostrar verde
      tramacoShippingApplied = true;
      TramacoStorage.markPageRefreshed();
    } else {
      tramacoShippingApplied = true;
    }
  } else if (
    typeof tramacoCartData !== "undefined" &&
    tramacoCartData.shippingApplied
  ) {
    tramacoShippingApplied = true;
  }

  // ============================================================
  // FUNCIÓN GLOBAL PARA FORZAR MENSAJE VERDE
  // Solo muestra verde si NO acaba de calcularse (es decir, ya hubo un refresh real)
  // ============================================================
  function forceShowGreenMessage() {
    var savedState = TramacoStorage.getShippingState();
    console.log("Tramaco: forceShowGreenMessage - estado:", {
      savedState: savedState,
      tramacoShippingApplied: tramacoShippingApplied,
      tramacoJustCalculated: tramacoJustCalculated,
    });

    // Si ACABA de calcularse en esta sesión, NO mostrar verde, mantener overlay
    if (tramacoJustCalculated) {
      console.log(
        "Tramaco: Envío recién calculado - mostrando overlay, NO mensaje verde",
      );
      $("#tramaco-cart-warning").hide();
      $("#tramaco-location-confirmed").hide();
      $("#tramaco-shipping-result").hide();
      $("#tramaco-applying-overlay").show();
      return false;
    }

    if ((savedState && savedState.applied) || tramacoShippingApplied) {
      console.log("Tramaco: Forzando mostrar mensaje verde", savedState);
      $("#tramaco-cart-warning").hide();
      $("#tramaco-location-confirmed").show();
      $("#tramaco-shipping-result").hide();
      $("#tramaco-applying-overlay").hide();
      return true;
    }
    console.log("Tramaco: No hay envío aplicado - mensaje verde NO mostrado");
    return false;
  }

  // ============================================================
  // MÓDULO DE CARRITO - Checkout en 2 pasos
  // ============================================================
  var TramacoCart = {
    ubicaciones: null,
    eventsBound: false,

    init: function () {
      // Verificar si estamos en la página del carrito
      if (!$("#tramaco-cart-location").length) {
        return;
      }

      // Verificar estado: si acaba de calcularse, mostrar overlay; si ya se refrescó, mostrar verde
      if (tramacoJustCalculated) {
        $("#tramaco-cart-warning").hide();
        $("#tramaco-location-confirmed").hide();
        $("#tramaco-shipping-result").hide();
        $("#tramaco-applying-overlay").show();
        console.log("Tramaco Cart: Envío recién calculado - mostrando overlay");
      } else {
        forceShowGreenMessage();
      }

      console.log("Tramaco Cart: Inicializando selector de ubicación...");

      // Usar datos pasados desde PHP
      if (typeof tramacoCartData !== "undefined") {
        this.ubicaciones = tramacoCartData.ubicaciones;
      }

      if (!this.ubicaciones || !this.ubicaciones.lstProvincia) {
        console.warn("Tramaco Cart: No se cargaron las ubicaciones");
        return;
      }

      console.log(
        "Tramaco Cart: Ubicaciones cargadas -",
        this.ubicaciones.lstProvincia.length,
        "provincias",
      );

      // Solo bindear eventos una vez para evitar duplicados
      if (!this.eventsBound) {
        this.bindEvents();
        this.eventsBound = true;
      }

      this.restoreSavedLocation();
    },

    bindEvents: function () {
      var self = this;

      // Cambio de provincia
      $(document).on("change", "#tramaco_cart_provincia", function () {
        self.onProvinciaChange($(this).val());
      });

      // Cambio de cantón
      $(document).on("change", "#tramaco_cart_canton", function () {
        self.onCantonChange($(this).val());
      });

      // Cambio de parroquia - calcular envío
      $(document).on("change", "#tramaco_cart_parroquia", function () {
        self.onParroquiaChange($(this).val());
      });
    },

    restoreSavedLocation: function () {
      var self = this;

      // Obtener estado de localStorage
      var savedStorageState = TramacoStorage.getShippingState();

      // Verificar si hay envío aplicado desde localStorage o PHP
      var hasShippingFromStorage =
        savedStorageState && savedStorageState.applied;
      var hasShippingFromPHP = tramacoCartData.shippingApplied;

      if (hasShippingFromStorage || hasShippingFromPHP) {
        console.log(
          "Tramaco Cart: Envío aplicado desde",
          hasShippingFromStorage ? "localStorage" : "PHP",
        );
        tramacoShippingApplied = true;
        $("#tramaco-cart-warning").hide();

        // Si acaba de calcularse, mostrar overlay; si ya se refrescó, mostrar verde
        if (tramacoJustCalculated) {
          $("#tramaco-location-confirmed").hide();
          $("#tramaco-applying-overlay").show();
        } else {
          $("#tramaco-location-confirmed").show();
          $("#tramaco-applying-overlay").hide();
        }
        $("#tramaco-shipping-result").hide();
      }

      // Usar datos de localStorage si están disponibles y PHP no tiene datos
      var provinciaToRestore =
        tramacoCartData.savedProvincia ||
        (savedStorageState && savedStorageState.provincia);
      var cantonToRestore =
        tramacoCartData.savedCanton ||
        (savedStorageState && savedStorageState.canton);
      var parroquiaToRestore =
        tramacoCartData.savedParroquia ||
        (savedStorageState && savedStorageState.parroquia);

      // Restaurar valores guardados
      if (provinciaToRestore) {
        console.log("Tramaco Cart: Restaurando provincia:", provinciaToRestore);
        $("#tramaco_cart_provincia").val(provinciaToRestore);
        this.onProvinciaChange(
          provinciaToRestore,
          function () {
            if (cantonToRestore) {
              console.log("Tramaco Cart: Restaurando cantón:", cantonToRestore);

              // Asegurar que el DOM esté actualizado antes de seleccionar cantón
              setTimeout(function () {
                var $cantonSelect = $("#tramaco_cart_canton");
                var cantonValue = String(cantonToRestore);

                // Verificar si la opción existe
                var optionExists =
                  $cantonSelect.find('option[value="' + cantonValue + '"]')
                    .length > 0;
                console.log(
                  "Tramaco Cart: Opción de cantón existe:",
                  optionExists,
                  "valor:",
                  cantonValue,
                );

                if (optionExists) {
                  $cantonSelect.val(cantonValue);
                  console.log(
                    "Tramaco Cart: Cantón establecido a:",
                    $cantonSelect.val(),
                  );
                }

                self.onCantonChange(
                  cantonToRestore,
                  function () {
                    if (parroquiaToRestore) {
                      console.log(
                        "Tramaco Cart: Restaurando parroquia:",
                        parroquiaToRestore,
                      );

                      // Asegurar que el DOM esté actualizado antes de seleccionar
                      setTimeout(function () {
                        var $parroquiaSelect = $("#tramaco_cart_parroquia");
                        var parroquiaValue = String(parroquiaToRestore);

                        // Verificar si la opción existe
                        var optionExists =
                          $parroquiaSelect.find(
                            'option[value="' + parroquiaValue + '"]',
                          ).length > 0;
                        console.log(
                          "Tramaco Cart: Opción de parroquia existe:",
                          optionExists,
                          "valor:",
                          parroquiaValue,
                        );

                        if (optionExists) {
                          $parroquiaSelect.val(parroquiaValue);
                          console.log(
                            "Tramaco Cart: Parroquia establecida a:",
                            $parroquiaSelect.val(),
                          );
                        }

                        // Si hay parroquia guardada y envío calculado
                        if (
                          hasShippingFromStorage ||
                          hasShippingFromPHP ||
                          tramacoShippingApplied
                        ) {
                          tramacoShippingApplied = true;
                          $("#tramaco-cart-warning").hide();
                          $("#tramaco-shipping-result").hide();

                          // Si acaba de calcularse, mantener overlay
                          if (tramacoJustCalculated) {
                            $("#tramaco-location-confirmed").hide();
                            $("#tramaco-applying-overlay").show();
                          } else {
                            $("#tramaco-location-confirmed").show();
                            $("#tramaco-applying-overlay").hide();
                          }
                          console.log(
                            "Tramaco Cart: Restauración completa - mostrando confirmación",
                          );
                        }
                      }, 50);
                    }
                  },
                  true,
                ); // isRestoring = true
              }, 50);
            }
          },
          true,
        ); // isRestoring = true
      }
    },

    onProvinciaChange: function (provinciaCode, callback, isRestoring) {
      var self = this;
      var $canton = $("#tramaco_cart_canton");
      var $parroquia = $("#tramaco_cart_parroquia");

      // Resetear cantón y parroquia
      $canton
        .html(
          '<option value="">' +
            tramacoCartData.i18n.firstSelectProvince +
            "</option>",
        )
        .prop("disabled", true);
      $parroquia
        .html(
          '<option value="">' +
            tramacoCartData.i18n.firstSelectCanton +
            "</option>",
        )
        .prop("disabled", true);

      // Ocultar resultado y mostrar advertencia (solo si no estamos restaurando)
      if (!isRestoring) {
        $("#tramaco-shipping-result").hide();
        $("#tramaco-cart-warning").show();
        $("#tramaco-cart-error").hide();
        $("#tramaco-location-confirmed").hide();
        $("#tramaco-applying-overlay").hide();
        // Resetear el estado de envío aplicado cuando cambia la provincia
        tramacoShippingApplied = false;
        // Limpiar localStorage cuando el usuario cambia la provincia
        TramacoStorage.clearShippingCost();
      }

      if (!provinciaCode) {
        if (!isRestoring) {
          this.saveLocation("", "", "");
        }
        return;
      }

      // Buscar provincia
      var provincia = this.ubicaciones.lstProvincia.find(function (p) {
        return p.codigo == provinciaCode;
      });

      if (provincia && provincia.lstCanton) {
        $canton.html(
          '<option value="">' + tramacoCartData.i18n.selectCanton + "</option>",
        );

        provincia.lstCanton.forEach(function (canton) {
          $canton.append(
            $("<option></option>").val(canton.codigo).text(canton.nombre),
          );
        });

        $canton.prop("disabled", false);
      }

      // Guardar provincia (solo si NO estamos restaurando para no borrar cantón/parroquia guardados)
      if (!isRestoring) {
        this.saveLocation(provinciaCode, "", "");
      }

      if (typeof callback === "function") {
        callback();
      }
    },

    onCantonChange: function (cantonCode, callback, isRestoring) {
      var self = this;
      var $provincia = $("#tramaco_cart_provincia");
      var $parroquia = $("#tramaco_cart_parroquia");
      var provinciaCode = $provincia.val();

      // Resetear parroquia
      $parroquia
        .html(
          '<option value="">' +
            tramacoCartData.i18n.firstSelectCanton +
            "</option>",
        )
        .prop("disabled", true);

      // Ocultar resultado (solo si no estamos restaurando)
      if (!isRestoring) {
        $("#tramaco-shipping-result").hide();
        $("#tramaco-cart-warning").show();
        $("#tramaco-location-confirmed").hide();
        $("#tramaco-applying-overlay").hide();
        // Resetear el estado de envío aplicado cuando cambia el cantón
        tramacoShippingApplied = false;
        // Limpiar localStorage cuando el usuario cambia el cantón
        TramacoStorage.clearShippingCost();
      }

      if (!cantonCode || !provinciaCode) {
        if (!isRestoring) {
          this.saveLocation(provinciaCode, "", "");
        }
        return;
      }

      // Buscar cantón
      var provincia = this.ubicaciones.lstProvincia.find(function (p) {
        return p.codigo == provinciaCode;
      });

      if (provincia) {
        var canton = provincia.lstCanton.find(function (c) {
          return c.codigo == cantonCode;
        });

        if (canton && canton.lstParroquia) {
          $parroquia.html(
            '<option value="">' +
              tramacoCartData.i18n.selectParroquia +
              "</option>",
          );

          canton.lstParroquia.forEach(function (parroquia) {
            $parroquia.append(
              $("<option></option>")
                .val(parroquia.codigo)
                .text(parroquia.nombre),
            );
          });

          $parroquia.prop("disabled", false);
        }
      }

      // Guardar provincia y cantón (solo si NO estamos restaurando para no borrar parroquia guardada)
      if (!isRestoring) {
        this.saveLocation(provinciaCode, cantonCode, "");
      }

      if (typeof callback === "function") {
        callback();
      }
    },

    onParroquiaChange: function (parroquiaCode) {
      var self = this;
      var provinciaCode = $("#tramaco_cart_provincia").val();
      var cantonCode = $("#tramaco_cart_canton").val();

      console.log("Tramaco Cart: onParroquiaChange llamado", {
        parroquia: parroquiaCode,
        provincia: provinciaCode,
        canton: cantonCode,
      });

      if (!parroquiaCode) {
        console.log(
          "Tramaco Cart: No hay parroquia seleccionada, mostrando advertencia",
        );
        $("#tramaco-shipping-result").hide();
        $("#tramaco-cart-warning").show();
        $("#tramaco-location-confirmed").hide();
        $("#tramaco-applying-overlay").hide();
        return;
      }

      console.log(
        "Tramaco Cart: Calculando envío para parroquia:",
        parroquiaCode,
      );

      // Guardar ubicación completa
      this.saveLocation(provinciaCode, cantonCode, parroquiaCode);

      // Calcular costo de envío
      this.calculateShipping(parroquiaCode);
    },

    saveLocation: function (provincia, canton, parroquia) {
      // Actualizar tramacoCartData inmediatamente para mantener sincronizado
      tramacoCartData.savedProvincia = provincia;
      tramacoCartData.savedCanton = canton;
      tramacoCartData.savedParroquia = parroquia;

      // Si no hay parroquia, resetear el estado de envío aplicado
      if (!parroquia) {
        tramacoCartData.shippingApplied = false;
        tramacoCartData.savedShippingCost = null;
        TramacoStorage.clearShippingCost();
      }

      $.ajax({
        url: tramacoCartData.ajaxUrl,
        type: "POST",
        data: {
          action: "tramaco_save_cart_location",
          provincia: provincia,
          canton: canton,
          parroquia: parroquia,
          nonce: tramacoCartData.nonce,
        },
        success: function (response) {
          console.log("Tramaco Cart: Ubicación guardada", response);
        },
        error: function (xhr, status, error) {
          console.error("Tramaco Cart: Error guardando ubicación:", error);
        },
      });
    },

    calculateShipping: function (parroquiaCode) {
      var self = this;

      console.log(
        "Tramaco Cart: calculateShipping iniciado para parroquia:",
        parroquiaCode,
      );

      // Mostrar indicador de carga
      $("#tramaco-calculating").show();
      $("#tramaco-shipping-result").hide();
      $("#tramaco-cart-error").hide();
      $("#tramaco-cart-warning").hide();
      $("#tramaco-location-confirmed").hide();

      // Obtener valores actuales para guardar en localStorage (usar guiones bajos, no guiones)
      var provinciaCode = $("#tramaco_cart_provincia").val();
      var cantonCode = $("#tramaco_cart_canton").val();

      console.log("Tramaco Cart: Enviando AJAX con datos:", {
        parroquia: parroquiaCode,
        provincia: provinciaCode,
        canton: cantonCode,
        url: tramacoCartData.ajaxUrl,
      });

      $.ajax({
        url: tramacoCartData.ajaxUrl,
        type: "POST",
        data: {
          action: "tramaco_cart_calculate_shipping",
          parroquia: parroquiaCode,
          nonce: tramacoCartData.nonce,
        },
        success: function (response) {
          $("#tramaco-calculating").hide();

          if (response.success) {
            console.log(
              "Tramaco Cart: Costo calculado:",
              response.data.total_formatted,
            );

            // Marcar que el envío fue aplicado Y que acaba de calcularse
            tramacoShippingApplied = true;
            tramacoJustCalculated = true; // NO mostrar verde hasta refresh real

            // Actualizar tramacoCartData para mantener sincronizado
            tramacoCartData.shippingApplied = true;
            tramacoCartData.savedShippingCost = response.data.total;

            // CRÍTICO: Guardar estado en localStorage para persistencia
            TramacoStorage.saveShippingState(
              provinciaCode,
              cantonCode,
              parroquiaCode,
              response.data.total,
            );

            console.log("Tramaco Cart: Estado guardado en localStorage", {
              provincia: provinciaCode,
              canton: cantonCode,
              parroquia: parroquiaCode,
              cost: response.data.total,
            });

            // Ocultar todo mientras se aplica el envío
            $("#tramaco-shipping-price").html(response.data.total_formatted);
            $("#tramaco-shipping-result").hide(); // Ocultar costo mientras carga
            $("#tramaco-cart-warning").hide();
            $("#tramaco-location-confirmed").hide(); // NO mostrar verde hasta después del refresh

            // Mostrar overlay de "aplicando envío" - esto es lo que el cliente ve mientras espera el refresh
            self.showApplyingOverlay();

            // Actualizar totales del carrito (esto recarga parcialmente la página)
            // El mensaje verde se mostrará después del refresh via localStorage
            setTimeout(function () {
              $(document.body).trigger("wc_update_cart");
              // NO ocultar el overlay aquí - se ocultará cuando la página se actualice
              // y el mensaje verde aparecerá desde el evento updated_wc_div
            }, 100);
          } else {
            console.error(
              "Tramaco Cart: Error en cálculo - respuesta del servidor:",
              response,
            );
            var errorMsg =
              response.data && response.data.message
                ? response.data.message
                : "Error desconocido";
            $("#tramaco-cart-error").text(errorMsg).show();
            $("#tramaco-cart-warning").show();
            $("#tramaco-location-confirmed").hide();
          }
        },
        error: function (xhr, status, error) {
          $("#tramaco-calculating").hide();
          console.error("Tramaco Cart: Error AJAX:", {
            status: status,
            error: error,
            responseText: xhr.responseText,
          });
          $("#tramaco-cart-error").text(tramacoCartData.i18n.error).show();
          $("#tramaco-cart-warning").show();
          $("#tramaco-location-confirmed").hide();
        },
      });
    },

    showApplyingOverlay: function () {
      console.log("Tramaco Cart: Mostrando overlay de aplicación de envío");

      // El overlay ya existe en el HTML de PHP, solo mostrarlo
      if ($("#tramaco-applying-overlay").length) {
        $("#tramaco-applying-overlay").show();
        console.log(
          "Tramaco Cart: Overlay visible:",
          $("#tramaco-applying-overlay").is(":visible"),
        );
      } else {
        // Por si acaso no existe, crearlo dinámicamente
        var overlayHtml =
          '<div class="tramaco-applying-overlay" id="tramaco-applying-overlay">' +
          '<div class="applying-content">' +
          '<div class="applying-spinner"></div>' +
          '<span class="applying-text">Aplicando envío al carrito...</span>' +
          "</div></div>";

        // Intentar insertar después de los campos de ubicación
        if ($("#tramaco-cart-location .tramaco-cart-location-fields").length) {
          $("#tramaco-cart-location .tramaco-cart-location-fields").after(
            overlayHtml,
          );
        } else if ($("#tramaco-cart-location").length) {
          $("#tramaco-cart-location").append(overlayHtml);
        }

        $("#tramaco-applying-overlay").show();
        console.log("Tramaco Cart: Overlay creado y mostrado");
      }
    },

    hideApplyingOverlay: function () {
      $("#tramaco-applying-overlay").hide();
    },
  };

  // ============================================================
  // MÓDULO DE CHECKOUT - Código existente mejorado
  // ============================================================
  var TramacoCheckout = {
    ubicaciones: null,
    currentPrefix: "shipping",

    init: function () {
      // Verificar que tramacoCheckout esté definido
      if (typeof tramacoCheckout === "undefined") {
        console.log(
          "Tramaco Checkout: tramacoCheckout no definido, saltando inicialización",
        );
        return;
      }

      // Verificar que estemos en una página con el formulario de checkout
      if (!$("form.woocommerce-checkout").length) {
        console.log(
          "Tramaco Checkout: No hay formulario de checkout, saltando",
        );
        return;
      }

      console.log("Tramaco Checkout: Inicializando...");

      this.ubicaciones = tramacoCheckout.ubicaciones;

      if (!this.ubicaciones || !this.ubicaciones.lstProvincia) {
        console.warn("Tramaco: No se cargaron las ubicaciones");
        console.log("Ubicaciones recibidas:", tramacoCheckout.ubicaciones);
        return;
      }

      console.log("Tramaco: Ubicaciones cargadas correctamente");
      console.log(
        "Provincias disponibles:",
        this.ubicaciones.lstProvincia.length,
      );

      this.bindEvents();
      this.initSelects();
      this.handleCountryVisibility();
    },

    handleCountryVisibility: function () {
      // Verificar país actual y mostrar/ocultar campos
      var billingCountry = $("#billing_country").val();
      var shippingCountry = $("#shipping_country").val();

      console.log("País Billing:", billingCountry);
      console.log("País Shipping:", shippingCountry);

      // Mostrar campos si el país es Ecuador
      if (billingCountry === "EC" || shippingCountry === "EC") {
        console.log("Tramaco: Mostrando campos para Ecuador");
        this.showTramacoFields();
      } else {
        console.log("Tramaco: Ocultando campos (país no es Ecuador)");
        this.hideTramacoFields();
      }
    },

    showTramacoFields: function () {
      $(".tramaco-field").removeClass("hidden").show();
      // Hacer campos requeridos
      $(
        "#shipping_tramaco_provincia, #shipping_tramaco_canton, #shipping_tramaco_parroquia",
      ).prop("required", true);
      $(
        "#billing_tramaco_provincia, #billing_tramaco_canton, #billing_tramaco_parroquia",
      ).prop("required", true);
    },

    hideTramacoFields: function () {
      $(".tramaco-field").addClass("hidden").hide();
      // Quitar requerido
      $(
        "#shipping_tramaco_provincia, #shipping_tramaco_canton, #shipping_tramaco_parroquia",
      ).prop("required", false);
      $(
        "#billing_tramaco_provincia, #billing_tramaco_canton, #billing_tramaco_parroquia",
      ).prop("required", false);
    },

    bindEvents: function () {
      var self = this;

      // Cambio de provincia (shipping_state) - Shipping
      $(document).on("change", "#shipping_state", function () {
        self.currentPrefix = "shipping";
        self.onProvinciaChange($(this).val(), "shipping");
      });

      // Cambio de cantón - Shipping
      $(document).on("change", "#shipping_tramaco_canton", function () {
        self.currentPrefix = "shipping";
        self.onCantonChange($(this).val(), "shipping");
      });

      // Cambio de parroquia - Shipping
      $(document).on("change", "#shipping_tramaco_parroquia", function () {
        self.currentPrefix = "shipping";
        self.onParroquiaChange($(this).val());
      });

      // Cambio de provincia (billing_state) - Billing
      $(document).on("change", "#billing_state", function () {
        self.currentPrefix = "billing";
        self.onProvinciaChange($(this).val(), "billing");
      });

      // Cambio de cantón - Billing
      $(document).on("change", "#billing_tramaco_canton", function () {
        self.currentPrefix = "billing";
        self.onCantonChange($(this).val(), "billing");
      });

      // Cambio de parroquia - Billing
      $(document).on("change", "#billing_tramaco_parroquia", function () {
        self.currentPrefix = "billing";
        self.onParroquiaChange($(this).val());
      });

      // Cuando cambia el checkbox de "Enviar a dirección diferente"
      $(document).on(
        "change",
        "#ship-to-different-address-checkbox",
        function () {
          if (!$(this).is(":checked")) {
            // Copiar valores de billing a shipping
            self.syncBillingToShipping();
          }
        },
      );

      // Actualizar cuando cambia el país
      $(document).on(
        "change",
        "#billing_country, #shipping_country",
        function () {
          var country = $(this).val();
          var prefix = $(this).attr("id").replace("_country", "");

          console.log(
            "Tramaco: Cambio de país detectado:",
            country,
            "Prefix:",
            prefix,
          );

          // Mostrar/ocultar campos Tramaco según el país
          if (country === "EC") {
            console.log("Tramaco: Mostrando campos para", prefix);
            $("#" + prefix + "_state_field")
              .removeClass("hidden")
              .show();
            $("#" + prefix + "_tramaco_canton_field")
              .removeClass("hidden")
              .show();
            $("#" + prefix + "_tramaco_parroquia_field")
              .removeClass("hidden")
              .show();

            // Hacer requeridos
            $("#" + prefix + "_state").prop("required", true);
            $("#" + prefix + "_tramaco_canton").prop("required", true);
            $("#" + prefix + "_tramaco_parroquia").prop("required", true);
          } else {
            console.log("Tramaco: Ocultando campos para", prefix);
            $("#" + prefix + "_state_field")
              .addClass("hidden")
              .hide();
            $("#" + prefix + "_tramaco_canton_field")
              .addClass("hidden")
              .hide();
            $("#" + prefix + "_tramaco_parroquia_field")
              .addClass("hidden")
              .hide();

            // Quitar requerido
            $("#" + prefix + "_state").prop("required", false);
            $("#" + prefix + "_tramaco_canton").prop("required", false);
            $("#" + prefix + "_tramaco_parroquia").prop("required", false);
          }

          // Ejecutar visibilidad general también
          self.handleCountryVisibility();
        },
      );

      // Trigger inicial para país
      console.log("Tramaco: Ejecutando trigger inicial de país");
      $("#billing_country, #shipping_country").trigger("change");
    },

    initSelects: function () {
      // Inicializar los selects si ya tienen valor
      var billingProvincia = $("#billing_state").val();
      var shippingProvincia = $("#shipping_state").val();

      if (billingProvincia) {
        this.onProvinciaChange(billingProvincia, "billing");
      }

      if (shippingProvincia) {
        this.onProvinciaChange(shippingProvincia, "shipping");
      }
    },

    onProvinciaChange: function (provinciaCode, prefix) {
      var self = this;
      var $canton = $("#" + prefix + "_tramaco_canton");
      var $parroquia = $("#" + prefix + "_tramaco_parroquia");

      // Resetear cantón y parroquia
      $canton.html(
        '<option value="">' + tramacoCheckout.i18n.selectCanton + "</option>",
      );
      $parroquia.html(
        '<option value="">' +
          tramacoCheckout.i18n.selectParroquia +
          "</option>",
      );

      if (!provinciaCode) {
        return;
      }

      // Buscar provincia
      var provincia = this.ubicaciones.lstProvincia.find(function (p) {
        return p.codigo == provinciaCode;
      });

      if (provincia && provincia.lstCanton) {
        provincia.lstCanton.forEach(function (canton) {
          $canton.append(
            $("<option></option>").val(canton.codigo).text(canton.nombre),
          );
        });
      }

      // Trigger para WooCommerce
      $canton.trigger("change");
    },

    onCantonChange: function (cantonCode, prefix) {
      var self = this;
      var $provincia = $("#" + prefix + "_state");
      var $parroquia = $("#" + prefix + "_tramaco_parroquia");
      var provinciaCode = $provincia.val();

      // Resetear parroquia
      $parroquia.html(
        '<option value="">' +
          tramacoCheckout.i18n.selectParroquia +
          "</option>",
      );

      if (!cantonCode || !provinciaCode) {
        return;
      }

      // Buscar cantón
      var provincia = this.ubicaciones.lstProvincia.find(function (p) {
        return p.codigo == provinciaCode;
      });

      if (provincia) {
        var canton = provincia.lstCanton.find(function (c) {
          return c.codigo == cantonCode;
        });

        if (canton && canton.lstParroquia) {
          canton.lstParroquia.forEach(function (parroquia) {
            $parroquia.append(
              $("<option></option>")
                .val(parroquia.codigo)
                .text(parroquia.nombre),
            );
          });
        }
      }

      // Trigger para WooCommerce
      $parroquia.trigger("change");
    },

    onParroquiaChange: function (parroquiaCode) {
      if (!parroquiaCode) {
        return;
      }

      // Guardar en sesión para el cálculo de envío
      this.saveToSession(parroquiaCode);
    },

    saveToSession: function (parroquiaCode) {
      var self = this;

      // Usar AJAX para guardar en la sesión de WooCommerce
      $.ajax({
        url: tramacoCheckout.ajaxUrl,
        type: "POST",
        data: {
          action: "tramaco_save_checkout_parroquia",
          parroquia: parroquiaCode,
          nonce: tramacoCheckout.nonce,
        },
        success: function (response) {
          console.log("Tramaco: Parroquia guardada en sesión:", parroquiaCode);
          // Actualizar costos de envío DESPUÉS de guardar
          self.updateShipping();
        },
        error: function (xhr, status, error) {
          console.error("Tramaco: Error guardando parroquia:", error);
          // Actualizar de todas formas
          self.updateShipping();
        },
      });
    },

    updateShipping: function () {
      // Forzar actualización del checkout de WooCommerce
      console.log("Tramaco: Actualizando checkout...");
      $(document.body).trigger("update_checkout");
    },

    syncBillingToShipping: function () {
      var billingProvincia = $("#billing_state").val();
      var billingCanton = $("#billing_tramaco_canton").val();
      var billingParroquia = $("#billing_tramaco_parroquia").val();

      if (billingProvincia) {
        $("#shipping_state").val(billingProvincia).trigger("change");

        // Esperar a que se carguen los cantones
        setTimeout(function () {
          if (billingCanton) {
            $("#shipping_tramaco_canton").val(billingCanton).trigger("change");

            // Esperar a que se carguen las parroquias
            setTimeout(function () {
              if (billingParroquia) {
                $("#shipping_tramaco_parroquia")
                  .val(billingParroquia)
                  .trigger("change");
              }
            }, 100);
          }
        }, 100);
      }
    },
  };

  // Inicializar cuando el DOM esté listo
  $(document).ready(function () {
    console.log("Tramaco: DOM listo, detectando página...");
    console.log(
      "Tramaco: Elemento carrito existe:",
      $("#tramaco-cart-location").length > 0,
    );
    console.log(
      "Tramaco: Elemento checkout existe:",
      $("form.woocommerce-checkout").length > 0,
    );
    console.log(
      "Tramaco: tramacoCartData definido:",
      typeof tramacoCartData !== "undefined",
    );
    console.log(
      "Tramaco: tramacoCheckout definido:",
      typeof tramacoCheckout !== "undefined",
    );

    // Inicializar módulo del carrito si existe el elemento
    if ($("#tramaco-cart-location").length > 0) {
      console.log("Tramaco: Inicializando módulo de CARRITO");
      TramacoCart.init();
    }

    // Inicializar módulo del checkout si existe el formulario
    if (
      $("form.woocommerce-checkout").length > 0 &&
      typeof tramacoCheckout !== "undefined"
    ) {
      console.log("Tramaco: Inicializando módulo de CHECKOUT");
      TramacoCheckout.init();
    }
  });

  // También inicializar cuando WooCommerce actualiza el checkout
  $(document).on("updated_checkout", function () {
    console.log("Tramaco: Evento updated_checkout detectado");
    if (typeof tramacoCheckout !== "undefined") {
      TramacoCheckout.initSelects();
    }
  });

  // Reinicializar carrito cuando se actualiza
  $(document).on("updated_wc_div wc_cart_updated", function () {
    console.log("Tramaco: Carrito actualizado, reinicializando...");

    // Pequeño delay para asegurar que el DOM esté completamente actualizado
    setTimeout(function () {
      // Ocultar overlay de aplicación DESPUÉS del delay
      $("#tramaco-applying-overlay").hide();

      // CRÍTICO: Usar función global para forzar estado correcto
      forceShowGreenMessage();

      // Inicializar carrito si existe
      if ($("#tramaco-cart-location").length > 0) {
        TramacoCart.init();
      }
    }, 500); // Delay más largo para que el usuario vea el mensaje de "Aplicando..."
  });

  // Evento disparado cuando el selector se inyecta para WooCommerce Blocks
  $(document).on("tramaco_cart_injected", function () {
    console.log("Tramaco: Evento tramaco_cart_injected detectado");
    setTimeout(function () {
      if ($("#tramaco-cart-location").length > 0) {
        console.log(
          "Tramaco: Inicializando módulo de carrito desde evento inyectado",
        );
        TramacoCart.init();
      }
    }, 100);
  });

  // Limpiar localStorage cuando el carrito se vacía o checkout se completa
  $(document).on("removed_from_cart cart_emptied", function () {
    console.log("Tramaco: Carrito modificado/vaciado, verificando estado...");
    // Si el carrito está vacío, limpiar localStorage
    setTimeout(function () {
      var cartItems = $(
        ".woocommerce-cart-form .cart_item, .wc-block-cart-items__row",
      ).length;
      if (cartItems === 0) {
        console.log("Tramaco: Carrito vacío, limpiando localStorage");
        TramacoStorage.remove("shipping_state");
        tramacoShippingApplied = false;
      }
    }, 500);
  });

  // Observer para detectar cuando se agrega el elemento dinámicamente (WooCommerce Blocks)
  if (typeof MutationObserver !== "undefined") {
    var cartInitialized = false;
    var observer = new MutationObserver(function (mutations) {
      // Verificar estado de mensajes cuando hay cambios en el DOM
      if (!tramacoJustCalculated) {
        var savedState = TramacoStorage.getShippingState();
        if ((savedState && savedState.applied) || tramacoShippingApplied) {
          var warningVisible = $("#tramaco-cart-warning").is(":visible");
          var confirmedHidden = !$("#tramaco-location-confirmed").is(
            ":visible",
          );

          if (warningVisible || confirmedHidden) {
            console.log("Tramaco Observer: Corrigiendo estado de mensajes");
            forceShowGreenMessage();
          }
        }
      }

      if (cartInitialized) return;

      mutations.forEach(function (mutation) {
        if (mutation.addedNodes.length) {
          if ($("#tramaco-cart-location").length > 0 && !cartInitialized) {
            cartInitialized = true;
            console.log(
              "Tramaco: Elemento de carrito detectado por MutationObserver",
            );
            TramacoCart.init();
          }
        }
      });
    });

    // Observar cambios en el body
    observer.observe(document.body, { childList: true, subtree: true });
  }

  // Verificar estado cada vez que el DOM esté listo (solo si no acaba de calcularse)
  $(function () {
    setTimeout(function () {
      if (!tramacoJustCalculated) {
        forceShowGreenMessage();
      }
    }, 200);
  });
})(jQuery);

/**
 * AJAX handler para guardar parroquia en sesión
 */
// Este handler se agrega en PHP, pero aquí documentamos su uso
