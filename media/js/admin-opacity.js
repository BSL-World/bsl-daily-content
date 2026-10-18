/**
 * @copyright Copyright (C) 2026 Vasilyev Alexander. All rights reserved.
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

(() => {
    'use strict';

    const initialize = () => {
        document.querySelectorAll('[data-bsl-opacity]').forEach((input) => {
            const output = document.getElementById(`${input.id}-value`);

            if (!output) {
                return;
            }

            const updateValue = () => {
                output.value = `${input.value}%`;
                output.textContent = `${input.value}%`;
            };

            input.addEventListener('input', updateValue);
            updateValue();
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
