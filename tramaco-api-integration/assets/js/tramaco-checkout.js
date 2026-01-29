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
  // MÓDULO DE CARRITO - Checkout en 2 pasos
  // ============================================================
  var TramacoCart = {
    ubicaciones: null,

    init: function () {
      // Verificar si estamos en la página del carrito
      if (!$("#tramaco-cart-location").length) {
        return;
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

      this.bindEvents();
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

      // Restaurar valores guardados
      if (tramacoCartData.savedProvincia) {
        console.log(
          "Tramaco Cart: Restaurando provincia:",
          tramacoCartData.savedProvincia,
        );
        $("#tramaco_cart_provincia").val(tramacoCartData.savedProvincia);
        this.onProvinciaChange(tramacoCartData.savedProvincia, function () {
          if (tramacoCartData.savedCanton) {
            console.log(
              "Tramaco Cart: Restaurando cantón:",
              tramacoCartData.savedCanton,
            );
            $("#tramaco_cart_canton").val(tramacoCartData.savedCanton);
            self.onCantonChange(tramacoCartData.savedCanton, function () {
              if (tramacoCartData.savedParroquia) {
                console.log(
                  "Tramaco Cart: Restaurando parroquia:",
                  tramacoCartData.savedParroquia,
                );
                $("#tramaco_cart_parroquia").val(
                  tramacoCartData.savedParroquia,
                );
                
                // Si hay parroquia guardada, mostrar mensaje de confirmación
                // y ocultar advertencia
                $("#tramaco-cart-warning").hide();
                $("#tramaco-location-confirmed").show();
              }
            });
          }
        });
      }
    },

    onProvinciaChange: function (provinciaCode, callback) {
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

      // Ocultar resultado y mostrar advertencia
      $("#tramaco-shipping-result").hide();
      $("#tramaco-cart-warning").show();
      $("#tramaco-cart-error").hide();
      $("#tramaco-location-confirmed").hide();
      $("#tramaco-applying-overlay").hide();

      if (!provinciaCode) {
        this.saveLocation("", "", "");
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

      // Guardar provincia
      this.saveLocation(provinciaCode, "", "");

      if (typeof callback === "function") {
        callback();
      }
    },

    onCantonChange: function (cantonCode, callback) {
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

      // Ocultar resultado
      $("#tramaco-shipping-result").hide();
      $("#tramaco-cart-warning").show();
      $("#tramaco-location-confirmed").hide();
      $("#tramaco-applying-overlay").hide();

      if (!cantonCode || !provinciaCode) {
        this.saveLocation(provinciaCode, "", "");
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

      // Guardar provincia y cantón
      this.saveLocation(provinciaCode, cantonCode, "");

      if (typeof callback === "function") {
        callback();
      }
    },

    onParroquiaChange: function (parroquiaCode) {
      var self = this;
      var provinciaCode = $("#tramaco_cart_provincia").val();
      var cantonCode = $("#tramaco_cart_canton").val();

      if (!parroquiaCode) {
        $("#tramaco-shipping-result").hide();
        $("#tramaco-cart-warning").show();
        $("#tramaco-location-confirmed").hide();
        $("#tramaco-applying-overlay").hide();
        return;
      }

      // Guardar ubicación completa
      this.saveLocation(provinciaCode, cantonCode, parroquiaCode);

      // Calcular costo de envío
      this.calculateShipping(parroquiaCode);
    },

    saveLocation: function (provincia, canton, parroquia) {
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

      // Mostrar indicador de carga
      $("#tramaco-calculating").show();
      $("#tramaco-shipping-result").hide();
      $("#tramaco-cart-error").hide();
      $("#tramaco-cart-warning").hide();
      $("#tramaco-location-confirmed").hide();

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

            // Mostrar resultado
            $("#tramaco-shipping-price").html(response.data.total_formatted);
            $("#tramaco-shipping-result").show();
            
            // Mostrar mensaje de confirmación en lugar de advertencia
            $("#tramaco-cart-warning").hide();
            $("#tramaco-location-confirmed").show();
            
            // Mostrar overlay de "aplicando envío"
            self.showApplyingOverlay();

            // Actualizar totales del carrito
            $(document.body).trigger("wc_update_cart");
          } else {
            console.error(
              "Tramaco Cart: Error en cálculo:",
              response.data.message,
            );
            $("#tramaco-cart-error").text(response.data.message).show();
            $("#tramaco-cart-warning").show();
          }
        },
        error: function (xhr, status, error) {
          $("#tramaco-calculating").hide();
          console.error("Tramaco Cart: Error AJAX:", error);
          $("#tramaco-cart-error").text(tramacoCartData.i18n.error).show();
          $("#tramaco-cart-warning").show();
        },
      });
    },
    
    showApplyingOverlay: function() {
      // Crear overlay si no existe
      if (!$("#tramaco-applying-overlay").length) {
        var overlayHtml = '<div class="tramaco-applying-overlay" id="tramaco-applying-overlay">' +
          '<div class="applying-content">' +
          '<div class="applying-spinner"></div>' +
          '<span class="applying-text">Aplicando envío al carrito...</span>' +
          '</div></div>';
        $("#tramaco-shipping-result").after(overlayHtml);
      }
      $("#tramaco-applying-overlay").show();
    },
    
    hideApplyingOverlay: function() {
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
    
    // Ocultar overlay de aplicación
    $("#tramaco-applying-overlay").hide();
    
    // Verificar si hay envío calculado (mirando si el resultado está visible o si hay precio)
    var shippingCalculated = $("#tramaco-shipping-result").is(":visible") || 
                              $("#tramaco-shipping-price").text().trim() !== "";
    var hasParroquia = $("#tramaco_cart_parroquia").val() !== "";
    
    if (shippingCalculated && hasParroquia) {
      $("#tramaco-cart-warning").hide();
      $("#tramaco-location-confirmed").show();
    }
    
    if ($("#tramaco-cart-location").length > 0) {
      TramacoCart.init();
    }
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

  // Observer para detectar cuando se agrega el elemento dinámicamente (WooCommerce Blocks)
  if (typeof MutationObserver !== "undefined") {
    var cartInitialized = false;
    var observer = new MutationObserver(function (mutations) {
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
})(jQuery);

/**
 * AJAX handler para guardar parroquia en sesión
 */
// Este handler se agrega en PHP, pero aquí documentamos su uso
