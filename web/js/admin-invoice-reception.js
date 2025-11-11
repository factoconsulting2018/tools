(function ($) {
    'use strict';

    const config = window.invoiceReceptionConfig || {};
    const credentialsForm = $('#mail-credentials-form');
    const requestForm = $('#invoice-request-form');
    const connectionStatus = $('#connection-status');
    const testConnectionBtn = $('#test-connection-btn');
    const openModalBtn = $('#open-request-modal-btn');
    const modalInstance = $('#invoice-request-modal');
    const modalSpinner = $('#modal-spinner');
    const modalStatus = $('#modal-status');
    const submitRequestBtn = $('#submit-invoice-request');
    const validateCertificateInput = $('#validate-certificate-value');
    const connectionSpinner = $('#connection-spinner');
    const portField = credentialsForm.find('input[name="MailReceptionForm[port]"]');
    const encryptionField = credentialsForm.find('input[name="MailReceptionForm[encryption]"]');
    const usernameField = credentialsForm.find('input[name="MailReceptionForm[username]"]');
    const emailField = credentialsForm.find('input[name="MailReceptionForm[email]"]');
    const passwordField = credentialsForm.find('input[name="MailReceptionForm[password]"]');
    const hostField = credentialsForm.find('input[name="MailReceptionForm[host]"]');
    const folderField = credentialsForm.find('input[name="MailReceptionForm[folder]"]');
    const labelField = credentialsForm.find('#credentials-label');
    const accountSelect = $('#mail-accounts-select');
    const createAccountBtn = $('#create-account-btn');
    const deleteAccountBtn = $('#delete-account-btn');
    const editAccountBtn = $('#edit-account-btn');
    const accountModal = $('#mail-account-modal');
    const accountForm = $('#mail-account-form');
    const accountStatus = $('#mail-account-status');
    const accountValidateInput = $('#mail-account-validate');
    const saveAccountBtn = $('#save-mail-account-btn');
    const accountFeedback = $('#account-feedback');

    let accounts = Array.isArray(config.accounts) ? config.accounts : [];
    let selectedAccountId = accountSelect.val() || '';

    let cachedCredentials = null;

    function normalizeForm(serialized) {
        const output = {};
        serialized.forEach(item => {
            const match = item.name.match(/\[(.+?)\]$/);
            if (match) {
                output[match[1]] = item.value;
            }
        });
        return output;
    }

    function serializeSimpleForm($form) {
        const data = {};
        $form.serializeArray().forEach(item => {
            if (item.name.indexOf('MailAccount[') === 0) {
                const key = item.name.substring(12, item.name.length - 1);
                data[key] = item.value;
            } else {
                data[item.name] = item.value;
            }
        });

        return data;
    }

    function getCredentialsData() {
        const data = normalizeForm(credentialsForm.serializeArray());
        if (validateCertificateInput.length) {
            const value = parseInt(validateCertificateInput.val(), 10);
            data.validateCertificate = Number.isNaN(value) ? 0 : value;
        } else {
            data.validateCertificate = 0;
        }
        if (labelField.length) {
            data.label = labelField.val();
        }
        return data;
    }

    function getRequestData() {
        return normalizeForm(requestForm.serializeArray());
    }

    function formatStatusMessage(type, message, details) {
        const icons = {
            success: '✔',
            danger: '✘',
            warning: '⚠',
            info: 'ℹ',
        };
        const icon = icons[type] || '';
        let text = icon ? `${icon} ${message}` : message;
        if (details && details.length) {
            text += ` (${details.join(', ')})`;
        }
        return text;
    }

    function setStatus(element, message, type, details = []) {
        const classes = ['text-success', 'text-danger', 'text-warning', 'text-info'];
        element.removeClass(classes.join(' '));
        if (type) {
            const map = {
                success: 'text-success',
                danger: 'text-danger',
                warning: 'text-warning',
                info: 'text-info',
            };
            if (map[type]) {
                element.addClass(map[type]);
            }
        }
        element.text(message ? formatStatusMessage(type, message, details) : '');
    }

    function setAlertStatus(element, type, message, details = []) {
        const alertClasses = ['alert-info', 'alert-success', 'alert-danger', 'alert-warning'];
        element.removeClass(alertClasses.join(' ')).removeClass('d-none');
        if (type) {
            element.addClass(`alert-${type}`);
        }
        element.text(message ? formatStatusMessage(type, message, details) : '');
    }

    function firstErrorFields(errors) {
        if (!errors || typeof errors !== 'object') {
            return [];
        }
        return Object.keys(errors)
            .filter(key => Array.isArray(errors[key]) && errors[key].length)
            .map(key => key);
    }

    async function callJson(url, payload, options) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': config.csrf || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
            ...options,
        });

        return response.json();
    }

    async function deleteArchive(id) {
        const url = `${config.deleteUrl}?id=${encodeURIComponent(id)}`;
        return callJson(url, {});
    }

    function buildCredentialAttempts(baseCredentials) {
        const attempts = [];
        const seen = new Set();

        const basePort = baseCredentials.port ? String(baseCredentials.port) : '';
        const baseEnc = baseCredentials.encryption || '';
        const rawValidate = typeof baseCredentials.validateCertificate === 'undefined'
            ? null
            : Boolean(parseInt(baseCredentials.validateCertificate, 10));

        const validationOrder = [];
        if (rawValidate !== null) {
            validationOrder.push(rawValidate);
            validationOrder.push(!rawValidate);
        }
        [true, false].forEach(value => {
            if (!validationOrder.includes(value)) {
                validationOrder.push(value);
            }
        });

        function queueAttempts(port, encryption) {
            validationOrder.forEach(validate => {
                const flag = validate ? 1 : 0;
                const key = `${port}-${encryption}-${flag}`;
                if (seen.has(key)) {
                    return;
                }
                seen.add(key);
                attempts.push({
                    ...baseCredentials,
                    port,
                    encryption,
                    validateCertificate: flag,
                });
            });
        }

        if (basePort && baseEnc) {
            queueAttempts(basePort, baseEnc);
        }

        [
            { port: '993', encryption: 'ssl' },
            { port: '993', encryption: 'tls' },
            { port: '993', encryption: 'none' },
            { port: '143', encryption: 'tls' },
            { port: '143', encryption: 'none' },
        ].forEach(combo => queueAttempts(combo.port, combo.encryption));

        return attempts;
    }

    function encryptionLabel(encryption) {
        switch (encryption) {
            case 'ssl':
                return 'SSL';
            case 'tls':
                return 'TLS';
            case 'none':
                return 'Sin cifrado';
            default:
                return encryption;
        }
    }

    function setFormValidateCertificate(isActive) {
        if (!validateCertificateInput.length) {
            return;
        }
        validateCertificateInput.val(isActive ? 1 : 0);
    }

    function setAccountValidateCertificate(isActive) {
        if (!accountValidateInput.length) {
            return;
        }
        accountValidateInput.val(isActive ? 1 : 0);
    }

    async function autoPersistSuccessfulAttempt(credentials) {
        if (!config.accountSaveUrl || !selectedAccountId) {
            return;
        }

        const account = getAccountById(selectedAccountId);
        if (!account) {
            return;
        }

        if (accountFeedback.length) {
            setStatus(accountFeedback, 'Actualizando la cuenta con la configuración verificada...', 'info');
        }

        const validateFlag = credentials.validateCertificate ? '1' : '0';

        setFormValidateCertificate(Boolean(credentials.validateCertificate));
        setAccountValidateCertificate(Boolean(credentials.validateCertificate));

        const params = new URLSearchParams();
        params.set('id', account.id);
        params.set('MailAccount[label]', labelField.val() || account.label || '');
        params.set('MailAccount[username]', usernameField.val() || account.username || '');
        params.set('MailAccount[email]', emailField.val() || account.email || '');
        params.set('MailAccount[password]', passwordField.val() || account.password || '');
        params.set('MailAccount[host]', hostField.val() || account.host || '');
        params.set('MailAccount[port]', credentials.port || account.port || '');
        params.set('MailAccount[encryption]', credentials.encryption || account.encryption || 'ssl');
        params.set('MailAccount[folder]', folderField.val() || account.folder || 'INBOX');
        params.set('MailAccount[validate_certificate]', validateFlag);
        if (config.csrf) {
            params.set('_csrf', config.csrf);
        }

        try {
            const response = await fetch(config.accountSaveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                cache: 'no-store',
                body: params.toString(),
            });

            const result = await response.json();
            if (!result || !result.success || !result.account) {
                const fields = firstErrorFields(result ? result.errors : null);
                if (accountFeedback.length) {
                    setStatus(
                        accountFeedback,
                        (result && result.message) || 'No fue posible actualizar la cuenta con la configuración detectada.',
                        'warning',
                        fields
                    );
                }
                return;
            }

            const updated = result.account;
            const index = accounts.findIndex(acc => String(acc.id) === String(updated.id));
            if (index >= 0) {
                accounts[index] = updated;
            } else {
                accounts.push(updated);
            }
            selectedAccountId = String(updated.id);
            refreshAccountsSelect();
            if (accountFeedback.length) {
                setStatus(accountFeedback, 'Cuenta actualizada con la configuración verificada.', 'success');
            }
        } catch (error) {
            console.error(error);
            if (accountFeedback.length) {
                setStatus(accountFeedback, 'Ocurrió un error al intentar guardar la configuración detectada.', 'danger');
            }
        }
    }

    function fillCredentialsFromAccount(account) {
        if (!account) {
            return;
        }
        if (labelField.length) {
            labelField.val(account.label || '');
        }
        if (usernameField.length) {
            usernameField.val(account.username || account.email || '');
        }
        emailField.val(account.email || '');
        passwordField.val(account.password || '');
        hostField.val(account.host || '');
        portField.val(account.port || '');
        encryptionField.val(account.encryption || 'ssl');
        folderField.val(account.folder || 'INBOX');
        setFormValidateCertificate(Boolean(account.validate_certificate));
        cachedCredentials = null;
    }

    function refreshAccountsSelect() {
        if (!accountSelect.length) {
            return;
        }

        accountSelect.empty();
        accountSelect.append('<option value="">Selecciona una cuenta guardada</option>');

        accounts.forEach(account => {
            const displayName = account.label && account.label.trim()
                ? account.label
                : (account.email && account.email.trim() ? account.email : '(Sin nombre)');
            const option = $('<option>')
                .val(account.id)
                .text(`${displayName} (${account.email || 'sin correo'})`);
            accountSelect.append(option);
        });

        if (selectedAccountId && !accounts.some(acc => String(acc.id) === String(selectedAccountId))) {
            selectedAccountId = '';
        }

        if (!selectedAccountId && accounts.length) {
            selectedAccountId = String(accounts[0].id);
        }

        accountSelect.val(selectedAccountId || '');
        const hasSelection = Boolean(selectedAccountId);
        deleteAccountBtn.prop('disabled', !hasSelection);
        editAccountBtn.prop('disabled', !hasSelection);

        const account = getAccountById(selectedAccountId);
        if (account) {
            fillCredentialsFromAccount(account);
            setStatus(accountFeedback, '', '');
        }
    }

    function getAccountById(id) {
        return accounts.find(account => String(account.id) === String(id));
    }

    testConnectionBtn.on('click', async function () {
        setStatus(connectionStatus, 'Preparando verificación...', 'warning');
        connectionSpinner.removeClass('hidden');
        openModalBtn.prop('disabled', true);
        testConnectionBtn.prop('disabled', true);

        try {
            const baseCredentials = getCredentialsData();
            const attempts = buildCredentialAttempts(baseCredentials);
            let successAttempt = null;
            let lastError = null;

            for (const attempt of attempts) {
                const certificateLabel = attempt.validateCertificate
                    ? 'validando certificado'
                    : 'sin validar certificado';
                setStatus(
                    connectionStatus,
                    `Probando puerto ${attempt.port} con ${encryptionLabel(attempt.encryption)} (${certificateLabel})...`,
                    'warning'
                );

                const result = await callJson(config.connectionUrl, attempt);
                if (result.success) {
                    successAttempt = attempt;
                    break;
                }
                lastError = result.message || 'Sin respuesta.';
            }

            if (successAttempt) {
                cachedCredentials = successAttempt;
                setFormValidateCertificate(Boolean(successAttempt.validateCertificate));
                setStatus(
                    connectionStatus,
                    `Conexión verificada con puerto ${successAttempt.port} (${encryptionLabel(successAttempt.encryption)}) ${successAttempt.validateCertificate ? 'validando certificado.' : 'sin validar certificado.'}`,
                    'success'
                );
                openModalBtn.prop('disabled', false);
                if (portField.length) {
                    portField.val(successAttempt.port);
                }
                if (encryptionField.length) {
                    encryptionField.val(successAttempt.encryption);
                }
                await autoPersistSuccessfulAttempt(successAttempt);
            } else {
                const message = lastError || 'No fue posible verificar la conexión con las combinaciones conocidas.';
                setStatus(connectionStatus, message, 'danger');
            }
        } catch (error) {
            console.error(error);
            setStatus(connectionStatus, 'Ocurrió un error inesperado. Revisa la consola.', 'danger');
        } finally {
            connectionSpinner.addClass('hidden');
            testConnectionBtn.prop('disabled', false);
        }
    });

    openModalBtn.on('click', function () {
        modalStatus.hide().removeClass('alert-success alert-danger alert-warning').text('');
        modalSpinner.hide();
        submitRequestBtn.prop('disabled', false);
        modalInstance.modal('show');
    });

    refreshAccountsSelect();

    accountSelect.on('change', function () {
        selectedAccountId = $(this).val();
        const hasSelection = Boolean(selectedAccountId);
        deleteAccountBtn.prop('disabled', !hasSelection);
        editAccountBtn.prop('disabled', !hasSelection);
        if (!hasSelection) {
            setStatus(accountFeedback, '', '');
        }
        const account = getAccountById(selectedAccountId);
        if (account) {
            fillCredentialsFromAccount(account);
            setStatus(accountFeedback, '', '');
        }
    });

    createAccountBtn.on('click', function () {
        if (!accountModal.length) {
            return;
        }

        accountForm[0].reset();
        accountStatus.addClass('d-none').text('');
        $('#mail-account-id').val('');
        $('#mail-account-label').val(usernameField.val() || emailField.val() || '');
        $('#mail-account-username').val(usernameField.val() || emailField.val() || '');
        $('#mail-account-email').val(emailField.val());
        $('#mail-account-password').val(passwordField.val());
        $('#mail-account-host').val(hostField.val());
        $('#mail-account-port').val(portField.val());
        $('#mail-account-encryption').val(encryptionField.val());
        $('#mail-account-folder').val(folderField.val());
        setAccountValidateCertificate(validateCertificateInput.val() === '1');
        accountModal.modal('show');
        setStatus(accountFeedback, '', '');
    });

    saveAccountBtn.on('click', async function () {
        if (!config.accountSaveUrl) {
            return;
        }

        const formElement = accountForm.get(0);
        const formData = new FormData(formElement);
        formData.set('MailAccount[validate_certificate]', accountValidateInput.val());
        if (config.csrf) {
            formData.set('_csrf', config.csrf);
        }

        const params = new URLSearchParams();
        formData.forEach(function (value, key) {
            params.append(key, String(value));
        });

        setAlertStatus(accountStatus, 'info', 'Guardando cuenta...');

        try {
        const response = await fetch(config.accountSaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin',
            cache: 'no-store',
            body: params.toString(),
        });

            const result = await response.json();
            if (!result || !result.success || !result.account || typeof result.account.id === 'undefined') {
                const fields = firstErrorFields(result ? result.errors : null);
                setAlertStatus(accountStatus, 'danger', (result && result.message) || 'No fue posible guardar la cuenta.', fields);
                return;
            }

            const saved = result.account;
            const existingIndex = accounts.findIndex(acc => String(acc.id) === String(saved.id));
            if (existingIndex >= 0) {
                accounts[existingIndex] = saved;
            } else {
                accounts.push(saved);
            }
            selectedAccountId = String(saved.id);
            refreshAccountsSelect();
            fillCredentialsFromAccount(saved);

            setAlertStatus(accountStatus, 'success', 'Cuenta guardada correctamente.');

            setTimeout(() => {
                accountModal.modal('hide');
                accountStatus.addClass('d-none').text('');
            }, 650);
        } catch (error) {
            console.error(error);
            setAlertStatus(accountStatus, 'danger', 'Ocurrió un error al guardar la cuenta.');
        }
    });

    editAccountBtn.on('click', function () {
        if (!selectedAccountId) {
            setStatus(accountFeedback, 'Selecciona una cuenta antes de editarla.', 'warning');
            return;
        }

        const account = getAccountById(selectedAccountId);
        if (!account) {
            setStatus(accountFeedback, 'No se encontró la cuenta seleccionada.', 'danger');
            return;
        }

        accountForm[0].reset();
        accountStatus.addClass('d-none').text('');
        $('#mail-account-id').val(account.id);
        $('#mail-account-label').val(account.label || '');
        $('#mail-account-username').val(account.username || '');
        $('#mail-account-email').val(account.email || '');
        $('#mail-account-password').val(account.password || '');
        $('#mail-account-host').val(account.host || '');
        $('#mail-account-port').val(account.port || 993);
        $('#mail-account-encryption').val(account.encryption || 'ssl');
        $('#mail-account-folder').val(account.folder || 'INBOX');
        setAccountValidateCertificate(Boolean(account.validate_certificate));
        accountModal.modal('show');
    });

    deleteAccountBtn.on('click', async function () {
        if (!selectedAccountId || !config.accountDeleteUrl) {
            setStatus(accountFeedback, 'Selecciona una cuenta antes de intentar eliminarla.', 'danger');
            return;
        }

        const confirmed = window.confirm('¿Seguro que deseas eliminar esta cuenta guardada?');
        if (!confirmed) {
            return;
        }

        try {
            const response = await fetch(config.accountDeleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': config.csrf || ''
                },
                body: `id=${encodeURIComponent(selectedAccountId)}`
            });
            const result = await response.json();
            if (!result.success) {
                setStatus(accountFeedback, result.message || 'No fue posible eliminar la cuenta.', 'danger', firstErrorFields(result.errors));
                return;
            }

            accounts = accounts.filter(acc => String(acc.id) !== String(selectedAccountId));
            selectedAccountId = '';
            refreshAccountsSelect();
            setStatus(accountFeedback, 'Cuenta eliminada correctamente.', 'success');
        } catch (error) {
            console.error(error);
            setStatus(accountFeedback, 'Ocurrió un error al eliminar la cuenta.', 'danger');
        }
    });

    submitRequestBtn.on('click', async function () {
        let credentialsPayload = cachedCredentials;
        if (!credentialsPayload) {
            credentialsPayload = getCredentialsData();
            cachedCredentials = credentialsPayload;
            modalStatus
                .show()
                .removeClass('alert-danger alert-warning')
                .addClass('alert-info')
                .text(formatStatusMessage('info', 'Usando las credenciales ingresadas sin verificación previa.'));
        }

        const requestData = getRequestData();

        modalSpinner.show();
        submitRequestBtn.prop('disabled', true);
        modalStatus.hide().removeClass('alert-success alert-danger alert-warning');

        try {
            const payload = {
                credentials: credentialsPayload,
                request: requestData
            };

            const result = await callJson(config.processUrl, payload);

            modalSpinner.hide();

            if (!result.success) {
                submitRequestBtn.prop('disabled', false);
                const credentialErrors = firstErrorFields(result.errors ? result.errors.credentials : null);
                modalStatus
                    .show()
                    .removeClass('alert-warning alert-success alert-info')
                    .addClass('alert-danger')
                    .text(formatStatusMessage('danger', result.message || 'No se pudo procesar la consulta. Revisa los datos e inténtalo de nuevo.', credentialErrors));
                return;
            }

            if (result.empty) {
                modalStatus
                    .show()
                    .removeClass('alert-danger alert-success alert-info')
                    .addClass('alert-warning')
                    .text(formatStatusMessage('warning', 'No se encontraron facturas en el periodo seleccionado.'));
                submitRequestBtn.prop('disabled', false);
                return;
            }

            modalStatus
                .show()
                .removeClass('alert-danger alert-warning alert-info')
                .addClass('alert-success')
                .text(formatStatusMessage('success', 'Se generó el archivo correctamente. Puedes descargarlo desde la tabla de registros.'));

            const outputType = requestData.outputType;
            modalInstance.modal('hide');

            setTimeout(async () => {
                if (outputType === 'zip' || outputType === 'rar') {
                    const remove = window.confirm('El archivo comprimido se generó correctamente. ¿Deseas eliminar el contenido después de descargarlo?');
                    if (remove && result.archiveId) {
                        await deleteArchive(result.archiveId);
                        alert('El archivo comprimido fue eliminado.');
                    } else {
                        alert('El archivo se mantendrá disponible para descarga en el historial.');
                    }
                } else if (result.downloadUrl) {
                    window.open(result.downloadUrl, '_blank');
                }

                window.location.reload();
            }, 400);
        } catch (error) {
            console.error(error);
            modalSpinner.hide();
            submitRequestBtn.prop('disabled', false);
            modalStatus
                .show()
                .removeClass('alert-warning alert-success alert-info')
                .addClass('alert-danger')
                .text(formatStatusMessage('danger', 'Ocurrió un error inesperado durante el procesamiento.'));
        }
    });

    $(document).on('click', '.js-delete-archive', async function () {
        const id = $(this).data('id');
        if (!id) {
            return;
        }

        const confirmed = window.confirm('¿Seguro que deseas eliminar este archivo generado? Esta acción no se puede deshacer.');
        if (!confirmed) {
            return;
        }

        try {
            const result = await deleteArchive(id);
            if (result.success) {
                $(`tr[data-archive-id="${id}"]`).fadeOut(200, function () {
                    $(this).remove();
                });
            } else {
                alert(result.message || 'No fue posible eliminar el archivo.');
            }
        } catch (error) {
            console.error(error);
            alert('Ocurrió un error al eliminar el archivo. Revisa la consola para más detalles.');
        }
    });
})(jQuery);

