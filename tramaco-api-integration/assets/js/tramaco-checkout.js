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
      this.ubicaciones = tramacoCheckout.ubicaciones;

      if (!this.ubicaciones || !this.ubicaciones.lstProvincia) {
        console.warn("Tramaco: No se cargaron las ubicaciones");
        return;
      }

      this.bindEvents();
      this.initSelects();
    },

    bindEvents: function () {
      var self = this;

      // Cambio de provincia - Shipping
      $(document).on("change", "#shipping_tramaco_provincia", function () {
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

      // Cambio de provincia - Billing
      $(document).on("change", "#billing_tramaco_provincia", function () {
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

          // Mostrar/ocultar campos Tramaco según el país
          if (country === "EC") {
            $("#" + prefix + "_tramaco_provincia_field").show();
            $("#" + prefix + "_tramaco_canton_field").show();
            $("#" + prefix + "_tramaco_parroquia_field").show();
          } else {
            $("#" + prefix + "_tramaco_provincia_field").hide();
            $("#" + prefix + "_tramaco_canton_field").hide();
            $("#" + prefix + "_tramaco_parroquia_field").hide();
          }
        },
      );

      // Trigger inicial para país
      $("#billing_country, #shipping_country").trigger("change");
    },

    initSelects: function () {
      // Inicializar los selects si ya tienen valor
      var billingProvincia = $("#billing_tramaco_provincia").val();
      var shippingProvincia = $("#shipping_tramaco_provincia").val();

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
      var $provincia = $("#" + prefix + "_tramaco_provincia");
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

      // Actualizar costos de envío
      this.updateShipping();
    },

    saveToSession: function (parroquiaCode) {
      // Usar AJAX para guardar en la sesión de WooCommerce
      $.ajax({
        url: tramacoCheckout.ajaxUrl,
        type: "POST",
        data: {
          action: "tramaco_save_checkout_parroquia",
          parroquia: parroquiaCode,
          nonce: tramacoCheckout.nonce,
        },
      });
    },

    updateShipping: function () {
      // Forzar actualización del checkout de WooCommerce
      $(document.body).trigger("update_checkout");
    },

    syncBillingToShipping: function () {
      var billingProvincia = $("#billing_tramaco_provincia").val();
      var billingCanton = $("#billing_tramaco_canton").val();
      var billingParroquia = $("#billing_tramaco_parroquia").val();

      if (billingProvincia) {
        $("#shipping_tramaco_provincia")
          .val(billingProvincia)
          .trigger("change");

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
