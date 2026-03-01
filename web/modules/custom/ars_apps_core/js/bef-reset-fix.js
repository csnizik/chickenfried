;(function (Drupal, once) {
  'use strict'

  Drupal.behaviors.befResetFix = {
    attach: function (context) {
      console.log('befResetFix attach called')

      // Find reset buttons - BEF uses various selectors.
      const resetSelectors = [
        'input[data-bef-auto-submit-click][id*="reset"]',
        'input[type="submit"][id*="reset"]',
        'button[id*="reset"]',
        '.bef-exposed-form input[value="Reset"]',
      ]

      resetSelectors.forEach(function (selector) {
        once('bef-reset-fix', selector, context).forEach(
          function (resetButton) {
            console.log('Found reset button:', resetButton)

            resetButton.addEventListener('click', function (e) {
              e.preventDefault() // Prevent default form submission
              console.log('Reset clicked')

              const form = resetButton.closest('form')
              if (!form) {
                console.log('No form found')
                return
              }
              console.log('Form found:', form)

              // Reset select elements.
              form.querySelectorAll('select').forEach(function (select) {
                console.log(
                  'Resetting select:',
                  select.name,
                  'from',
                  select.value,
                )
                select.selectedIndex = 0
              })

              // Clear text inputs.
              form
                .querySelectorAll('input[type="text"], input[type="search"]')
                .forEach(function (input) {
                  console.log('Clearing input:', input.name)
                  input.value = ''
                })

              // Reset radios.
              form
                .querySelectorAll('input[type="radio"]')
                .forEach(function (input) {
                  if (input.value === 'All' || input.value === 'all') {
                    input.checked = TRUE
                  } else {
                    input.checked = FALSE
                  }
                })

              // Reset checkboxes.
              form
                .querySelectorAll('input[type="checkbox"]')
                .forEach(function (input) {
                  input.checked = FALSE
                })

              // Navigate to base path without query parameters (true reset)
              window.location.href = form.getAttribute('action')
            })
          },
        )
      })
    },
  }
})(Drupal, once)
