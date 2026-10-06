/**
 * @copyright Copyright (C) 2026 Vasilyev Alexander. All rights reserved.
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

(() => {
    'use strict';

    const initialize = () => {
        const categoryField = document.getElementById('jform_catid');
        const options = Joomla.getOptions(
            'plg_content_bsldailycontent.admin',
            {},
        );
        const categoryIds = Array.isArray(options.categoryIds)
            ? options.categoryIds.map(String)
            : [];
        const fieldName = typeof options.fieldName === 'string'
            ? options.fieldName
            : '';

        if (!categoryField || categoryIds.length === 0 || fieldName === '') {
            return;
        }

        let requestId = 0;

        const openDailyContentTab = (currentRequestId, attempt = 0) => {
            if (
                currentRequestId !== requestId
                || !categoryIds.includes(String(categoryField.value))
            ) {
                return;
            }

            const escapedFieldName = CSS.escape(fieldName);
            const dateField = document.querySelector(
                `[name="jform[com_fields][${escapedFieldName}]"]`,
            );
            const tabPanel = dateField?.closest('joomla-tab-element');
            const tabButton = tabPanel?.id
                ? document.querySelector(
                    `button[aria-controls="${CSS.escape(tabPanel.id)}"]`,
                )
                : null;

            if (tabButton && !tabButton.hidden) {
                tabButton.click();
                dateField.focus({ preventScroll: true });
                tabPanel.scrollIntoView({ block: 'start', behavior: 'smooth' });
                return;
            }

            if (attempt < 50) {
                window.setTimeout(
                    () => openDailyContentTab(currentRequestId, attempt + 1),
                    100,
                );
            }
        };

        categoryField.addEventListener('change', () => {
            requestId += 1;

            if (!categoryIds.includes(String(categoryField.value))) {
                return;
            }

            openDailyContentTab(requestId);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
