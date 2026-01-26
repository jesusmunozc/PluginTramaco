/**
 * Scripts para el checkout de WooCommerce con Tramaco
 *
 * Maneja:
 * - Selects dinámicos de ubicación (provincia/cantón/parroquia)
 * - Actualización de costos de envío en tiempo real
 *
 * @package Tramaco_API_Integration
 * @since 1.1.0
 */

(function ($) {
  "use strict";

  var TramacoCheckout = {
    ubicaciones: null,
    currentPrefix: "shipping",

    init: function () {
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
    TramacoCheckout.init();
  });

  // También inicializar cuando WooCommerce actualiza el checkout
  $(document).on("updated_checkout", function () {
    TramacoCheckout.initSelects();
  });
})(jQuery);

/**
 * AJAX handler para guardar parroquia en sesión
 */
// Este handler se agrega en PHP, pero aquí documentamos su uso
