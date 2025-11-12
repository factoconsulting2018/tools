// Slides Carousel Auto-rotation
function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }
    return String(value).replace(/[&<>"']/g, function (char) {
        switch (char) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#039;';
            default: return char;
        }
    });
}

let haciendaForm;
let submitButton;
let haciendaModal;
let modalTitle;
let modalBody;
let copyActivitiesBtn;
let copyAllBtn;
let typeSelect;
let identificacionInput;
let toggleMassBtn;
let massPanel;
let massInput;
let massSubmitBtn;
let massStatusEl;
let massSpinner;
let massStatusText;
let massPrevBtn;
let massNextBtn;
let massIndicator;
let massExportBtn;
const appConfig = window.appConfig || {};
const LOG_USAGE_URL = appConfig.logUsageUrl || null;
const CSRF_TOKEN = appConfig.csrfToken || (window.yii && typeof yii.getCsrfToken === 'function' ? yii.getCsrfToken() : null);

let defaultTypeValue = 'fisica';
let massResults = [];
let currentMassIndex = -1;
let currentResultSource = 'none';

const BCCR_ENDPOINT = 'https://gee.bccr.fi.cr/Indicadores/Suscripciones/WS/wsindicadoreseconomicos.asmx/ObtenerIndicadoresEconomicos';
const BCCR_EMAIL = appConfig.bccrEmail || '';
const BCCR_TOKEN = appConfig.bccrToken || '';
const BCCR_NOMBRE = appConfig.bccrNombre || '';
const BCCR_MONTH_NAMES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

function logUsage(entries) {
    if (!LOG_USAGE_URL || !Array.isArray(entries) || !entries.length) {
        return Promise.resolve();
    }

    const headers = {
        'Content-Type': 'application/json'
    };
    if (CSRF_TOKEN) {
        headers['X-CSRF-Token'] = CSRF_TOKEN;
    }

    return fetch(LOG_USAGE_URL, {
        method: 'POST',
        headers,
        body: JSON.stringify({ entries })
    }).catch(function () {
        // ignore logging failures
    });
}

function formatListItem(label, value, key) {
    if (!value) {
        return '';
    }
    const dataAttr = key ? ' data-campo="' + key + '"' : '';
    return '<li class="hacienda-actividad"' + dataAttr + '>' +
        '<span class="hacienda-actividad__codigo">' + escapeHtml(label) + '</span>' +
        '<span class="hacienda-actividad__descripcion">' + escapeHtml(value) + '</span>' +
    '</li>';
}

function openModal(modal, titleEl, bodyEl, contentHtml, isError = false) {
    if (!modal || !titleEl || !bodyEl) {
        return;
    }
    modal.classList.toggle('error', isError);
    bodyEl.innerHTML = contentHtml;
    if (isError) {
        titleEl.textContent = 'Aviso de la consulta';
    }
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    document.body.classList.add('modal-open');
}

function closeModal(modal) {
    if (!modal) {
        return;
    }
    modal.classList.add('hidden');
    modal.classList.remove('error');
    document.body.style.overflow = '';
    document.body.classList.remove('modal-open');
}

function hasTypeOption(value) {
    if (!typeSelect) {
        return false;
    }
    return Array.from(typeSelect.options).some(function (option) {
        return option.value === value;
    });
}

function detectTypeFromIdent(identificacion) {
    const digits = (identificacion || '').replace(/\D+/g, '');
    if (!digits) {
        return defaultTypeValue;
    }
    if (digits.startsWith('3101') || (digits.length === 10 && digits[0] === '3')) {
        return hasTypeOption('juridica') ? 'juridica' : defaultTypeValue;
    }
    if (digits.length >= 11 && hasTypeOption('dimex')) {
        return 'dimex';
    }
    return defaultTypeValue;
}

function buildRequestParams(type, identificacion) {
    const formData = haciendaForm ? new FormData(haciendaForm) : new FormData();
    const resolvedType = hasTypeOption(type) ? type : defaultTypeValue;
    formData.set('HaciendaSearchForm[type]', resolvedType);
    formData.set('HaciendaSearchForm[identificacion]', identificacion);
    const params = new URLSearchParams();
    formData.forEach(function (value, key) {
        if (typeof value === 'string') {
            params.set(key, value);
        }
    });
    return params;
}

function requestHaciendaRecord(type, identificacion) {
    const params = buildRequestParams(type, identificacion);
    const actionUrl = haciendaForm ? haciendaForm.action : 'site/buscar-cedula';
    return fetch(actionUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: params.toString(),
    }).then(function (response) {
        return response.json();
    });
}

function buildModalSections(data) {
    const sections = [];
    const generalItems = [];

    const generalFields = [
        ['Identificación', data.identificacion, 'identificacion'],
        ['Tipo', data.tipoIdentificacion, 'tipo'],
        ['Nombre', data.nombre, 'nombre'],
        ['Situación tributaria', data.situacionTributaria, 'situacion'],
        ['Régimen', data.regimen ? (typeof data.regimen === 'object'
            ? [data.regimen.codigo, data.regimen.descripcion].filter(Boolean).join(' - ')
            : data.regimen) : '', 'regimen'],
        ['Última actualización', data.ultimaActualizacion, 'actualizacion']
    ];

    generalFields.forEach(function (field) {
        const item = formatListItem(field[0], field[1], field[2]);
        if (item) {
            generalItems.push(item);
        }
    });

    if (generalItems.length) {
        sections.push({
            id: 'generales',
            html: '<div class="hacienda-modal__section" data-section="generales"><h5>Datos generales</h5><ul class="hacienda-modal__list">' + generalItems.join('') + '</ul></div>'
        });
    }

    if (data.domicilioFiscal && typeof data.domicilioFiscal === 'object') {
        const domicilio = data.domicilioFiscal;
        const domicilioTexto = [domicilio.otrasSenas, domicilio.distrito, domicilio.canton, domicilio.provincia]
            .filter(Boolean)
            .map(escapeHtml)
            .join(', ');
        if (domicilioTexto) {
            sections.push({
                id: 'domicilio',
                html: '<div class="hacienda-modal__section" data-section="domicilio"><h5>Domicilio fiscal</h5><p>' + domicilioTexto + '</p></div>'
            });
        }
    }

    if (data.situacion && typeof data.situacion === 'object') {
        const situacionItems = [];
        const situacionLabels = {
            estado: 'Estado',
            moroso: 'Moroso',
            omiso: 'Omiso',
            domicilio: 'Domicilio',
            ubicacion: 'Ubicación',
            administracionTributaria: 'Administración tributaria'
        };

        Object.keys(situacionLabels).forEach(function (key) {
            if (key in data.situacion && data.situacion[key] !== null && data.situacion[key] !== '') {
                const value = typeof data.situacion[key] === 'string'
                    ? data.situacion[key]
                    : String(data.situacion[key]);
                situacionItems.push(
                    '<li class="hacienda-actividad" data-campo="situacion-' + key + '">' +
                        '<span class="hacienda-actividad__codigo">' + situacionLabels[key] + '</span>' +
                        '<span class="hacienda-actividad__descripcion">' + escapeHtml(value) + '</span>' +
                    '</li>'
                );
            }
        });

        if (situacionItems.length) {
            sections.push({
                id: 'situacion-detalle',
                html: '<div class="hacienda-modal__section" data-section="situacion-detalle"><h5>Situación en Hacienda</h5><ul class="hacienda-modal__list">' + situacionItems.join('') + '</ul></div>'
            });
        }
    }

    if (Array.isArray(data.actividades) && data.actividades.length) {
        const actividades = data.actividades.slice(0, 8).map(function (actividad) {
            const descripcion = actividad.descripcion || '';
            const codigo = actividad.codigo || '';
            const estado = actividad.estado || '';
            const estadoTexto = estado ? ' (' + escapeHtml(estado) + ')' : '';
            return (
                '<li class="hacienda-actividad" data-codigo="' + escapeHtml(codigo) + '">' +
                    '<span class="hacienda-actividad__codigo">' + escapeHtml(codigo || '') + '</span>' +
                    '<span class="hacienda-actividad__descripcion">' + escapeHtml(descripcion) + estadoTexto + '</span>' +
                '</li>'
            );
        }).join('');

        let extra = '';
        if (data.actividades.length > 8) {
            extra = '<p class="text-muted">Se muestran las primeras actividades registradas.</p>';
        }

        sections.push({
            id: 'actividades',
            html: '<div class="hacienda-modal__section" data-section="actividades"><h5>Actividades económicas</h5><ul class="hacienda-modal__list hacienda-actividades-list">' + actividades + '</ul>' + extra + '</div>'
        });
    }

    if (!sections.length) {
        sections.push({
            id: 'vacio',
            html: '<div class="hacienda-modal__section" data-section="vacio"><p>No se encontraron detalles adicionales para esta identificación.</p></div>'
        });
    }

    return sections;
}

function renderModalContent(sections, titleText) {
    if (!modalBody || !modalTitle) {
        return '';
    }

    modalTitle.textContent = titleText;
    const html = sections.map(function (section) {
        return section.html;
    }).join('');

    modalBody.innerHTML = html;
    return html;
}

function buildCopyTextFromModal() {
    if (!modalBody) {
        return '';
    }

    const sections = modalBody.querySelectorAll('.hacienda-modal__section');
    const parts = [];

    sections.forEach(function (section) {
        const sectionLines = [];
        const heading = section.querySelector('h5');
        if (heading) {
            sectionLines.push(heading.textContent.trim());
        }

        const rows = section.querySelectorAll('.hacienda-actividad');
        rows.forEach(function (row) {
            const label = row.querySelector('.hacienda-actividad__codigo');
            const value = row.querySelector('.hacienda-actividad__descripcion');
            if (label && value) {
                sectionLines.push(label.textContent.trim() + ': ' + value.textContent.trim());
            } else {
                sectionLines.push(row.textContent.trim());
            }
        });

        Array.from(section.children).forEach(function (child) {
            if (child.tagName === 'P') {
                const text = child.textContent.trim();
                if (text) {
                    sectionLines.push(text);
                }
            }
        });

        if (sectionLines.length) {
            parts.push(sectionLines.join('\n'));
        }
    });

    return parts.join('\n\n');
}

function getFirstElement(parent, tag) {
    if (!parent) {
        return null;
    }
    const elements = parent.getElementsByTagName(tag);
    return elements.length ? elements[0] : null;
}

function getFirstText(parent, tag) {
    const node = getFirstElement(parent, tag);
    return node ? node.textContent.trim() : '';
}

function buildDireccion(node) {
    if (!node) {
        return '';
    }
    const parts = [
        getFirstText(node, 'Provincia'),
        getFirstText(node, 'Canton'),
        getFirstText(node, 'Distrito'),
        getFirstText(node, 'Barrio'),
        getFirstText(node, 'OtrasSenas')
    ].filter(Boolean);
    return parts.join(', ');
}

function parseFacturaXML(xmlDoc) {
    const root = xmlDoc.documentElement;
    if (!root) {
        throw new Error('Estructura XML inválida.');
    }

    const general = {
        clave: getFirstText(root, 'Clave'),
        numero: getFirstText(root, 'NumeroConsecutivo'),
        fecha: getFirstText(root, 'FechaEmision'),
        codigoActividad: getFirstText(root, 'CodigoActividad') || getFirstText(root, 'CodigoActividadComercial')
    };

    const emisorNode = getFirstElement(root, 'Emisor');
    const emisor = emisorNode ? {
        nombre: getFirstText(emisorNode, 'Nombre'),
        tipoId: getFirstText(getFirstElement(emisorNode, 'Identificacion'), 'Tipo'),
        numeroId: getFirstText(getFirstElement(emisorNode, 'Identificacion'), 'Numero'),
        nombreComercial: getFirstText(emisorNode, 'NombreComercial'),
        correo: getFirstText(emisorNode, 'CorreoElectronico'),
        telefono: getFirstText(getFirstElement(emisorNode, 'Telefono'), 'NumTelefono'),
        direccion: buildDireccion(getFirstElement(emisorNode, 'Ubicacion'))
    } : {};

    const receptorNode = getFirstElement(root, 'Receptor');
    const receptor = receptorNode ? {
        nombre: getFirstText(receptorNode, 'Nombre'),
        tipoId: getFirstText(getFirstElement(receptorNode, 'Identificacion'), 'Tipo'),
        numeroId: getFirstText(getFirstElement(receptorNode, 'Identificacion'), 'Numero'),
        correo: getFirstText(receptorNode, 'CorreoElectronico'),
        telefono: getFirstText(getFirstElement(receptorNode, 'Telefono'), 'NumTelefono'),
        direccion: buildDireccion(getFirstElement(receptorNode, 'Ubicacion'))
    } : {};

    const resumenNode = getFirstElement(root, 'ResumenFactura');
    const resumen = resumenNode ? {
        totalVenta: getFirstText(resumenNode, 'TotalVenta'),
        totalDescuentos: getFirstText(resumenNode, 'TotalDescuentos'),
        totalImpuesto: getFirstText(resumenNode, 'TotalImpuesto'),
        totalComprobante: getFirstText(resumenNode, 'TotalComprobante'),
        totalGravado: getFirstText(resumenNode, 'TotalGravado'),
        totalExento: getFirstText(resumenNode, 'TotalExento')
    } : {};

    const detalleNodes = root.getElementsByTagName('LineaDetalle');
    const detalle = Array.from(detalleNodes).map(function (linea) {
        const impuestoNode = getFirstElement(linea, 'Impuesto');
        return {
            numero: getFirstText(linea, 'NumeroLinea') || String(detalle.length + 1),
            codigo: getFirstText(linea, 'Codigo'),
            detalle: getFirstText(linea, 'Detalle'),
            cantidad: getFirstText(linea, 'Cantidad'),
            unidad: getFirstText(linea, 'UnidadMedida'),
            precio: getFirstText(linea, 'PrecioUnitario'),
            subtotal: getFirstText(linea, 'SubTotal'),
            impuesto: impuestoNode ? getFirstText(impuestoNode, 'Monto') : '',
            totalLinea: getFirstText(linea, 'MontoTotalLinea')
        };
    });

    return {
        general,
        emisor,
        receptor,
        resumen,
        detalle
    };
}

function buildXmlInvoiceSections(invoice) {
    const sections = [];
    const generalItems = [];
    generalItems.push(formatListItem('Clave', invoice.general.clave, 'xml-clave'));
    generalItems.push(formatListItem('Consecutivo', invoice.general.numero, 'xml-numero'));
    generalItems.push(formatListItem('Fecha de emisión', invoice.general.fecha, 'xml-fecha'));
    generalItems.push(formatListItem('Código actividad', invoice.general.codigoActividad, 'xml-actividad'));

    sections.push({
        id: 'xml-generales',
        html: '<div class="hacienda-modal__section" data-section="xml-generales"><h5>Datos generales</h5><ul class="hacienda-modal__list">' + generalItems.join('') + '</ul></div>'
    });

    const emisorItems = [];
    emisorItems.push(formatListItem('Nombre', invoice.emisor.nombre, 'xml-emisor-nombre'));
    if (invoice.emisor.tipoId || invoice.emisor.numeroId) {
        emisorItems.push(formatListItem('Identificación', [invoice.emisor.tipoId, invoice.emisor.numeroId].filter(Boolean).join(' - '), 'xml-emisor-id'));
    }
    emisorItems.push(formatListItem('Nombre comercial', invoice.emisor.nombreComercial, 'xml-emisor-comercial'));
    emisorItems.push(formatListItem('Correo', invoice.emisor.correo, 'xml-emisor-correo'));
    emisorItems.push(formatListItem('Teléfono', invoice.emisor.telefono, 'xml-emisor-telefono'));
    emisorItems.push(formatListItem('Dirección', invoice.emisor.direccion, 'xml-emisor-direccion'));

    sections.push({
        id: 'xml-emisor',
        html: '<div class="hacienda-modal__section" data-section="xml-emisor"><h5>Emisor</h5><ul class="hacienda-modal__list">' + emisorItems.join('') + '</ul></div>'
    });

    const receptorItems = [];
    receptorItems.push(formatListItem('Nombre', invoice.receptor.nombre, 'xml-receptor-nombre'));
    if (invoice.receptor.tipoId || invoice.receptor.numeroId) {
        receptorItems.push(formatListItem('Identificación', [invoice.receptor.tipoId, invoice.receptor.numeroId].filter(Boolean).join(' - '), 'xml-receptor-id'));
    }
    receptorItems.push(formatListItem('Correo', invoice.receptor.correo, 'xml-receptor-correo'));
    receptorItems.push(formatListItem('Teléfono', invoice.receptor.telefono, 'xml-receptor-telefono'));
    receptorItems.push(formatListItem('Dirección', invoice.receptor.direccion, 'xml-receptor-direccion'));

    sections.push({
        id: 'xml-receptor',
        html: '<div class="hacienda-modal__section" data-section="xml-receptor"><h5>Receptor</h5><ul class="hacienda-modal__list">' + receptorItems.join('') + '</ul></div>'
    });

    const resumenItems = [];
    resumenItems.push(formatListItem('Total venta', invoice.resumen.totalVenta, 'xml-resumen-venta'));
    resumenItems.push(formatListItem('Total descuentos', invoice.resumen.totalDescuentos, 'xml-resumen-descuentos'));
    resumenItems.push(formatListItem('Total impuesto', invoice.resumen.totalImpuesto, 'xml-resumen-impuesto'));
    resumenItems.push(formatListItem('Total comprobante', invoice.resumen.totalComprobante, 'xml-resumen-comprobante'));

    sections.push({
        id: 'xml-resumen',
        html: '<div class="hacienda-modal__section" data-section="xml-resumen"><h5>Resumen</h5><ul class="hacienda-modal__list">' + resumenItems.join('') + '</ul></div>'
    });

    if (invoice.detalle.length) {
        const detalleHtml = invoice.detalle.map(function (item) {
            const detalles = [];
            if (item.cantidad) {
                detalles.push('Cantidad: ' + item.cantidad + (item.unidad ? ' ' + item.unidad : ''));
            }
            if (item.precio) {
                detalles.push('Precio: ' + item.precio);
            }
            if (item.subtotal) {
                detalles.push('Subtotal: ' + item.subtotal);
            }
            if (item.impuesto) {
                detalles.push('Impuesto: ' + item.impuesto);
            }
            if (item.totalLinea) {
                detalles.push('Total línea: ' + item.totalLinea);
            }
            const complemento = detalles.length ? '<br><small>' + escapeHtml(detalles.join(' • ')) + '</small>' : '';
            return '<li class="hacienda-actividad" data-codigo="' + escapeHtml(item.numero) + '">' +
                '<span class="hacienda-actividad__codigo">Línea ' + escapeHtml(item.numero) + '</span>' +
                '<span class="hacienda-actividad__descripcion">' +
                    escapeHtml(item.detalle || item.codigo || '') + complemento +
                '</span>' +
            '</li>';
        }).join('');

        sections.push({
            id: 'xml-detalle',
            html: '<div class="hacienda-modal__section" data-section="xml-detalle"><h5>Detalle de líneas</h5><ul class="hacienda-modal__list hacienda-actividades-list">' + detalleHtml + '</ul></div>'
        });
    }

    return sections;
}

function formatCurrencyCRC(value) {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }
    return new Intl.NumberFormat('es-CR', { style: 'currency', currency: 'CRC', minimumFractionDigits: 2 }).format(value);
}

function getLastCompleteMonthRange(referenceDate) {
    const today = referenceDate ? new Date(referenceDate) : new Date();
    const firstDayCurrent = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDayPrevious = new Date(firstDayCurrent.getTime() - 86400000);
    const firstDayPrevious = new Date(lastDayPrevious.getFullYear(), lastDayPrevious.getMonth(), 1);

    const start = firstDayPrevious;
    const end = lastDayPrevious;

    const format = (date) => {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    };

    const label = `${BCCR_MONTH_NAMES[start.getMonth()]} ${start.getFullYear()}`;

    return {
        startDate: start,
        endDate: end,
        start: format(start),
        end: format(end),
        label
    };
}

function parseBccrXmlResponse(xmlText, indicator) {
    const parser = new DOMParser();
    const xmlDoc = parser.parseFromString(xmlText, 'application/xml');
    if (xmlDoc.getElementsByTagName('parsererror').length) {
        throw new Error('Respuesta inválida del BCCR.');
    }

    const elements = Array.from(xmlDoc.getElementsByTagName('*'));
    const rows = [];

    elements.forEach(function (elem) {
        const children = Array.from(elem.children);
        if (!children.length) {
            return;
        }

        const findChild = function (needle) {
            return children.find(function (child) {
                return child.tagName && child.tagName.toLowerCase().includes(needle);
            });
        };

        const valorNode = findChild('num_valor');
        const fechaNode = findChild('des_fecha');

        if (valorNode && fechaNode) {
            try {
                const rawValor = valorNode.textContent ? valorNode.textContent.trim().replace(',', '.') : '';
                const valor = Number.parseFloat(rawValor);
                const fechaTexto = fechaNode.textContent ? fechaNode.textContent.trim().split(' ')[0] : '';
                const [dd, mm, yyyy] = fechaTexto.split('/').map(function (part) { return Number.parseInt(part, 10); });
                if (Number.isNaN(valor) || Number.isNaN(dd) || Number.isNaN(mm) || Number.isNaN(yyyy)) {
                    return;
                }
                const fecha = new Date(yyyy, mm - 1, dd);
                rows.push({
                    fecha,
                    fechaStr: fechaTexto,
                    valor,
                    indicador
                });
            } catch (error) {
                // omit invalid rows
            }
        }
    });

    rows.sort(function (a, b) {
        return a.fecha.getTime() - b.fecha.getTime();
    });

    return rows;
}

async function fetchBccrIndicator(indicador, fechaInicio, fechaFin) {
    const params = new URLSearchParams({
        Indicador: indicador,
        FechaInicio: fechaInicio,
        FechaFinal: fechaFin,
        Nombre: BCCR_NOMBRE,
        SubNiveles: 'N',
        CorreoElectronico: BCCR_EMAIL,
        Token: BCCR_TOKEN
    });

    const response = await fetch(`${BCCR_ENDPOINT}?${params.toString()}`, {
        method: 'GET',
        headers: {
            Accept: 'application/xml'
        }
    });

    if (!response.ok) {
        throw new Error('Error consultando el BCCR (' + response.status + ').');
    }

    const xmlText = await response.text();
    return parseBccrXmlResponse(xmlText, indicador);
}

function buildDollarSections(data) {
    const sections = [];
    const compra = data.compra || [];
    const venta = data.venta || [];
    const range = data.range || {};

    const promedio = function (items) {
        if (!items.length) {
            return null;
        }
        const sum = items.reduce(function (acc, item) { return acc + item.valor; }, 0);
        return sum / items.length;
    };

    const maximo = function (items) {
        if (!items.length) {
            return null;
        }
        return Math.max.apply(null, items.map(function (item) { return item.valor; }));
    };

    const minimo = function (items) {
        if (!items.length) {
            return null;
        }
        return Math.min.apply(null, items.map(function (item) { return item.valor; }));
    };

    const resumenItems = [];
    resumenItems.push(formatListItem('Rango consultado', `${range.start || ''} al ${range.end || ''} (${range.label || ''})`, 'dolar-rango'));
    resumenItems.push(formatListItem('Días consultados', String(Math.max(compra.length, venta.length)), 'dolar-dias'));
    resumenItems.push(formatListItem('Compra promedio (317)', formatCurrencyCRC(promedio(compra)), 'dolar-prom-compra'));
    resumenItems.push(formatListItem('Venta promedio (318)', formatCurrencyCRC(promedio(venta)), 'dolar-prom-venta'));
    resumenItems.push(formatListItem('Compra mínima', formatCurrencyCRC(minimo(compra)), 'dolar-min-compra'));
    resumenItems.push(formatListItem('Compra máxima', formatCurrencyCRC(maximo(compra)), 'dolar-max-compra'));
    resumenItems.push(formatListItem('Venta mínima', formatCurrencyCRC(minimo(venta)), 'dolar-min-venta'));
    resumenItems.push(formatListItem('Venta máxima', formatCurrencyCRC(maximo(venta)), 'dolar-max-venta'));

    sections.push({
        id: 'dolar-resumen',
        html: '<div class="hacienda-modal__section" data-section="dolar-resumen"><h5>Resumen del tipo de cambio</h5><ul class="hacienda-modal__list">' + resumenItems.join('') + '</ul></div>'
    });

    const mergedMap = new Map();
    compra.forEach(function (item) {
        const key = item.fecha.getTime();
        mergedMap.set(key, {
            fecha: item.fecha,
            fechaStr: item.fechaStr,
            compra: item.valor,
            venta: null
        });
    });
    venta.forEach(function (item) {
        const key = item.fecha.getTime();
        if (!mergedMap.has(key)) {
            mergedMap.set(key, {
                fecha: item.fecha,
                fechaStr: item.fechaStr,
                compra: null,
                venta: item.valor
            });
        } else {
            const current = mergedMap.get(key);
            current.venta = item.valor;
        }
    });

    const tabla = Array.from(mergedMap.values()).sort(function (a, b) {
        return a.fecha.getTime() - b.fecha.getTime();
    });

    const tablaHtml = tabla.map(function (item) {
        return '<tr>' +
            `<td>${escapeHtml(item.fechaStr || '')}</td>` +
            `<td>${escapeHtml(formatCurrencyCRC(item.compra))}</td>` +
            `<td>${escapeHtml(formatCurrencyCRC(item.venta))}</td>` +
        '</tr>';
    }).join('');

    sections.push({
        id: 'dolar-tabla',
        html: '<div class="hacienda-modal__section" data-section="dolar-tabla"><h5>Detalle diario</h5><table class="hacienda-table"><thead><tr><th>Fecha</th><th>Compra (₡)</th><th>Venta (₡)</th></tr></thead><tbody>' + tablaHtml + '</tbody></table></div>'
    });

    return sections;
}

function parseXmlFile(file) {
    return new Promise(function (resolve, reject) {
        const reader = new FileReader();
        reader.onload = function () {
            try {
                const parser = new DOMParser();
                const xmlDoc = parser.parseFromString(reader.result, 'application/xml');
                if (xmlDoc.getElementsByTagName('parsererror').length) {
                    throw new Error('El archivo no es un XML válido.');
                }
                const invoice = parseFacturaXML(xmlDoc);
                const sections = buildXmlInvoiceSections(invoice);
                resolve({
                    source: 'xml',
                    success: true,
                    fileName: file.name,
                    title: invoice.general.numero || invoice.general.clave || file.name,
                    sections,
                    invoice
                });
            } catch (error) {
                reject(error);
            }
        };
        reader.onerror = function () {
            reject(new Error('No se pudo leer el archivo ' + file.name));
        };
        reader.readAsText(file);
    });
}

async function loadXmlInvoices(files) {
    const listado = Array.from(files || []);
    if (!listado.length) {
        return;
    }

    currentResultSource = 'xml';
    if (massSpinner) {
        massSpinner.classList.remove('hidden');
    }
    if (massStatusText) {
        massStatusText.textContent = 'Procesando ' + listado.length + ' archivo(s) XML...';
    }

    const results = [];
    let success = 0;
    let failures = 0;
    const usageEntries = [];
    const usageEntries = [];

    for (let i = 0; i < listado.length; i += 1) {
        const file = listado[i];
        if (massStatusText) {
            massStatusText.textContent = 'Procesando ' + (i + 1) + ' de ' + listado.length + '...';
        }
        try {
            // eslint-disable-next-line no-await-in-loop
            const parsed = await parseXmlFile(file);
            results.push(parsed);
            success += 1;
            usageEntries.push({ type: 'xml', identifier: parsed.title || parsed.fileName || file.name });
        } catch (error) {
            results.push({
                source: 'xml',
                success: false,
                title: file.name,
                error: error.message || 'No se pudo procesar el archivo.'
            });
            failures += 1;
            usageEntries.push({ type: 'xml_error', identifier: file.name });
        }
    }

    massResults = results;
    currentMassIndex = -1;
    updateMassControls();

    if (results.length) {
        displayMassResult(0);
    } else {
        clearMassState();
    }

    if (usageEntries.length) {
        logUsage(usageEntries);
    }

    if (massSpinner) {
        massSpinner.classList.add('hidden');
    }
    if (massStatusText) {
        massStatusText.textContent = 'Carga de XML finalizada (' + success + ' éxito(s), ' + failures + ' error(es)).';
    }
}

async function loadDollarData() {
    if (!BCCR_EMAIL || !BCCR_TOKEN || !BCCR_NOMBRE) {
        const message = '<p>Configura las credenciales del BCCR para utilizar esta función.</p><p>Define <code>BCCR_EMAIL</code>, <code>BCCR_TOKEN</code> y <code>BCCR_NOMBRE</code> en tus variables de entorno o en <code>config/params.php</code>.</p>';
        openModal(haciendaModal, modalTitle, modalBody, message, true);
        if (modalTitle) {
            modalTitle.textContent = 'Precio del Dólar';
        }
        return;
    }

    const range = getLastCompleteMonthRange();
    if (massSpinner) {
        massSpinner.classList.remove('hidden');
    }
    if (massStatusText) {
        massStatusText.textContent = 'Consultando tipo de cambio del BCCR...';
    }

    try {
        const [compra, venta] = await Promise.all([
            fetchBccrIndicator(317, range.start, range.end),
            fetchBccrIndicator(318, range.start, range.end)
        ]);

        const sections = buildDollarSections({ compra, venta, range });
        massResults = [{
            source: 'dolar',
            success: true,
            title: range.label,
            sections,
            range,
            compra,
            venta
        }];
        currentMassIndex = -1;
        currentResultSource = 'dolar';
        updateMassControls();
        displayMassResult(0);
        if (massStatusText) {
            massStatusText.textContent = 'Consulta finalizada (' + compra.length + ' datos de compra, ' + venta.length + ' datos de venta).';
        }
        logUsage([{
            type: 'dolar',
            identifier: range.label,
            metadata: {
                datosCompra: compra.length,
                datosVenta: venta.length
            }
        }]);
    } catch (error) {
        clearMassState();
        currentResultSource = 'none';
        const message = '<p>No se pudo obtener el tipo de cambio del BCCR.</p><p>' + escapeHtml(error.message || '') + '</p>';
        openModal(haciendaModal, modalTitle, modalBody, message, true);
        if (modalTitle) {
            modalTitle.textContent = 'Precio del Dólar';
        }
        logUsage([{ type: 'dolar_error', identifier: range.label, metadata: { error: error.message || '' } }]);
    } finally {
        if (massSpinner) {
            massSpinner.classList.add('hidden');
        }
        if (massSubmitBtn && massSubmitBtn.dataset && massSubmitBtn.dataset.originalText) {
            massSubmitBtn.textContent = massSubmitBtn.dataset.originalText;
            massSubmitBtn.disabled = false;
        }
    }
}

function buildWorkbookRows() {
    if (currentResultSource !== 'hacienda') {
        return [];
    }
    const successful = massResults.filter(function (result) {
        return result.success && result.data;
    });

    if (!successful.length) {
        return [];
    }

    return successful.map(function (result) {
        const data = result.data;
        const regimenText = data.regimen
            ? (typeof data.regimen === 'object'
                ? [data.regimen.codigo, data.regimen.descripcion].filter(Boolean).join(' - ')
                : data.regimen)
            : '';
        const situacion = data.situacion || {};
        const morosoValue = (situacion.moroso || '').toString().toUpperCase() === 'SI' ? 'Moroso' : 'Al día';
        const actividades = Array.isArray(data.actividades)
            ? data.actividades.map(function (act) {
                  const codigo = act.codigo || '';
                  const descripcion = act.descripcion || '';
                  const estado = act.estado ? ' (' + act.estado + ')' : '';
                  return (codigo ? codigo + ' ' : '') + descripcion + estado;
              }).join(' | ')
            : '';

        return {
            'Identificación': result.identificacion || '',
            'Tipo': data.tipoIdentificacion || '',
            'Nombre': data.nombre || '',
            'Régimen': regimenText,
            'Situación': situacion.estado || data.situacionTributaria || '',
            'Morosidad': morosoValue,
            'Omiso': (situacion.omiso || '').toString().toUpperCase() === 'SI' ? 'Omiso' : 'Al día',
            'Administración Tributaria': situacion.administracionTributaria || '',
            'Actividades': actividades,
        };
    });
}

function exportMassResultsToExcel() {
    const rows = buildWorkbookRows();
    if (!rows.length) {
        console.warn('No hay resultados exitosos para exportar.');
        return;
    }

    const headers = [
        'Identificación',
        'Tipo',
        'Nombre',
        'Régimen',
        'Situación',
        'Morosidad',
        'Omiso',
        'Administración Tributaria',
        'Actividades',
    ];

    const worksheet = XLSX.utils.json_to_sheet(rows, { header: headers });
    const rowCount = rows.length + 1;
    worksheet['!autofilter'] = { ref: 'A1:I' + rowCount };
    worksheet['!freeze'] = { pane: { ySplit: 1, topLeftCell: 'A2', activePane: 'bottomLeft', state: 'frozen' } };
    worksheet['!cols'] = [
        { wch: 15 },
        { wch: 8 },
        { wch: 40 },
        { wch: 30 },
        { wch: 18 },
        { wch: 12 },
        { wch: 12 },
        { wch: 28 },
        { wch: 60 },
    ];

    const headerFill = {
        patternType: 'solid',
        fgColor: { rgb: '1F4E78' },
        bgColor: { rgb: '1F4E78' }
    };
    const headerFont = {
        bold: true,
        color: { rgb: 'FFFFFF' }
    };
    const headerAlignment = {
        vertical: 'center',
        horizontal: 'center'
    };

    headers.forEach(function (_, idx) {
        const cellAddress = XLSX.utils.encode_cell({ r: 0, c: idx });
        const cell = worksheet[cellAddress];
        if (cell) {
            cell.s = {
                fill: headerFill,
                font: headerFont,
                alignment: headerAlignment
            };
        }
    });

    const firstColumnFill = {
        patternType: 'solid',
        fgColor: { rgb: '1B2B5E' },
        bgColor: { rgb: '1B2B5E' }
    };
    const firstColumnFont = {
        color: { rgb: 'FFFFFF' }
    };
    for (let row = 1; row <= rowCount; row += 1) {
        const cellAddress = XLSX.utils.encode_cell({ r: row, c: 0 });
        const cell = worksheet[cellAddress];
        if (cell) {
            cell.s = Object.assign({}, cell.s || {}, {
                fill: firstColumnFill,
                font: Object.assign({}, cell.s && cell.s.font ? cell.s.font : {}, firstColumnFont)
            });
        }
    }

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Consulta Hacienda');
    XLSX.writeFile(workbook, 'consulta-hacienda.xlsx', { compression: true });
}

function copyTextToClipboard(text) {
    if (!text) {
        return Promise.resolve();
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try {
            const successful = document.execCommand('copy');
            document.body.removeChild(textarea);
            if (successful) {
                resolve();
            } else {
                reject(new Error('No se pudo copiar el texto.'));
            }
        } catch (err) {
            document.body.removeChild(textarea);
            reject(err);
        }
    });
}

function buildCopyText() {
    return buildCopyTextFromModal();
}

function buildCsvContent() {
    const successful = massResults.filter(function (result) {
        return result.success && result.data;
    });

    if (!successful.length) {
        return '';
    }

    const headers = [
        'Identificación',
        'Tipo',
        'Nombre',
        'Régimen',
        'Estado',
        'Moroso',
        'Omiso',
        'Administración Tributaria',
        'Actividades',
    ];

    function escapeCell(value) {
        const stringValue = value === null || value === undefined ? '' : String(value);
        if (/[",\n]/.test(stringValue)) {
            return '"' + stringValue.replace(/"/g, '""') + '"';
        }
        return stringValue;
    }

    const rows = successful.map(function (result) {
        const data = result.data;
        const regimenText = data.regimen
            ? (typeof data.regimen === 'object'
                ? [data.regimen.codigo, data.regimen.descripcion].filter(Boolean).join(' - ')
                : data.regimen)
            : '';
        const situacion = data.situacion || {};
        const actividades = Array.isArray(data.actividades)
            ? data.actividades.map(function (act) {
                  const codigo = act.codigo || '';
                  const descripcion = act.descripcion || '';
                  const estado = act.estado ? ' (' + act.estado + ')' : '';
                  return codigo + ' ' + descripcion + estado;
              }).join(' | ')
            : '';

        return [
            result.identificacion || '',
            data.tipoIdentificacion || '',
            data.nombre || '',
            regimenText,
            situacion.estado || '',
            situacion.moroso || '',
            situacion.omiso || '',
            situacion.administracionTributaria || '',
            actividades,
        ].map(escapeCell).join(',');
    });

    return headers.map(escapeCell).join(',') + '\r\n' + rows.join('\r\n');
}
function clearMassState() {
    massResults = [];
    currentMassIndex = -1;
    currentResultSource = 'none';
    if (massIndicator) {
        massIndicator.textContent = '';
    }
    if (massPrevBtn) {
        massPrevBtn.disabled = true;
    }
    if (massNextBtn) {
        massNextBtn.disabled = true;
    }
    if (massExportBtn) {
        massExportBtn.disabled = true;
    }
    if (massStatusText) {
        massStatusText.textContent = '';
    }
    if (massSpinner) {
        massSpinner.classList.add('hidden');
    }
}

function updateMassControls() {
    const total = massResults.length;
    if (massIndicator) {
        massIndicator.textContent = total > 0 && currentMassIndex >= 0
            ? (currentMassIndex + 1) + ' de ' + total
            : '';
    }
    if (massPrevBtn) {
        massPrevBtn.disabled = !(total > 1 && currentMassIndex > 0);
    }
    if (massNextBtn) {
        massNextBtn.disabled = !(total > 1 && currentMassIndex < total - 1);
    }
    if (massExportBtn) {
        const hasSuccess = currentResultSource === 'hacienda' && massResults.some(function (result) {
            return result && result.success && result.source !== 'xml';
        });
        massExportBtn.disabled = !hasSuccess;
    }
}

function displayMassResult(index) {
    if (!massResults.length || !haciendaModal) {
        return;
    }

    currentMassIndex = Math.max(0, Math.min(index, massResults.length - 1));
    const result = massResults[currentMassIndex];

    if (result.source === 'dolar') {
        if (result.success && result.sections) {
            const rangeLabel = result.range && result.range.label ? result.range.label : '';
            const title = rangeLabel ? 'Precio del Dólar - ' + rangeLabel : 'Precio del Dólar';
            const html = renderModalContent(result.sections, title);
            openModal(haciendaModal, modalTitle, modalBody, html);
        } else {
            const message = result.error || 'No se pudo obtener el tipo de cambio del BCCR.';
            openModal(haciendaModal, modalTitle, modalBody, '<p>' + escapeHtml(message) + '</p>', true);
            if (modalTitle) {
                modalTitle.textContent = 'Precio del Dólar';
            }
        }
        updateMassControls();
        return;
    }

    if (result.source === 'xml') {
        if (result.success && result.sections) {
            const numero = result.title || result.fileName || '';
            const modalTitleText = numero ? 'Factura Electrónica: ' + numero : 'Factura Electrónica';
            const html = renderModalContent(result.sections, modalTitleText);
            openModal(haciendaModal, modalTitle, modalBody, html);
        } else {
            const message = result.error || 'No se pudo procesar el archivo.';
            openModal(haciendaModal, modalTitle, modalBody, '<p>' + escapeHtml(message) + '</p>', true);
            if (modalTitle) {
                modalTitle.textContent = 'Factura Electrónica';
            }
        }
        updateMassControls();
        return;
    }

    if (result.success && result.data) {
        const title = result.data.nombre
            ? 'Contribuyente: ' + result.data.nombre
            : 'Consulta: ' + result.identificacion;
        const html = renderModalContent(buildModalSections(result.data), title);
        openModal(haciendaModal, modalTitle, modalBody, html);
    } else {
        const message = result.error || 'No se pudo obtener información para ' + result.identificacion;
        openModal(haciendaModal, modalTitle, modalBody, '<p>' + escapeHtml(message) + '</p>', true);
        if (modalTitle) {
            modalTitle.textContent = 'Consulta: ' + result.identificacion;
        }
    }

    updateMassControls();
}

function showSingleResult(response, type, identificacion) {
    currentResultSource = 'hacienda';
    if (response.success && response.data) {
        clearMassState();
        const title = response.data.nombre ? 'Contribuyente: ' + response.data.nombre : 'Resultado de la consulta';
        const html = renderModalContent(buildModalSections(response.data), title);
        openModal(haciendaModal, modalTitle, modalBody, html);
        logUsage([{ type: 'hacienda', identifier: identificacion }]);
    } else {
        const message = response.error || 'No fue posible completar la consulta.';
        clearMassState();
        openModal(haciendaModal, modalTitle, modalBody, '<p>' + escapeHtml(message) + '</p>', true);
        logUsage([{ type: 'hacienda_error', identifier: identificacion }]);
    }

    updateMassControls();
}

async function runMassQuery(identificadores) {
    if (!identificadores.length) {
        if (massStatusText) {
            massStatusText.textContent = 'Ingrese al menos una identificación.';
        }
        if (massSpinner) {
            massSpinner.classList.add('hidden');
        }
        return;
    }

    if (massStatusText) {
        massStatusText.textContent = '';
    }
    if (massSpinner) {
        massSpinner.classList.remove('hidden');
    }
    if (massSubmitBtn) {
        massSubmitBtn.disabled = true;
        massSubmitBtn.dataset.originalText = massSubmitBtn.dataset.originalText || massSubmitBtn.textContent;
        massSubmitBtn.textContent = 'Consultando...';
    }
    massResults = [];
    currentMassIndex = -1;
    updateMassControls();
    if (massExportBtn) {
        massExportBtn.disabled = true;
    }

    let success = 0;
    let failures = 0;

    for (let i = 0; i < identificadores.length; i += 1) {
        const ident = identificadores[i];
        const type = detectTypeFromIdent(ident);
        if (massStatusText) {
            massStatusText.textContent = 'Consultando ' + (i + 1) + ' de ' + identificadores.length + '...';
        }
        // Permite que el navegador refresque la UI antes de iniciar cada petición
        // para que el spinner y el texto de estado se actualicen correctamente.
        // eslint-disable-next-line no-await-in-loop
        await new Promise(function (resolve) {
            if (typeof requestAnimationFrame !== 'undefined') {
                requestAnimationFrame(resolve);
            } else {
                setTimeout(resolve, 16);
            }
        });
        try {
            // eslint-disable-next-line no-await-in-loop
            const response = await requestHaciendaRecord(type, ident);
            if (response.success && response.data) {
                massResults.push({
                    source: 'hacienda',
                    identificacion: ident,
                    type: type,
                    success: true,
                    data: response.data,
                });
                success += 1;
                usageEntries.push({ type: 'hacienda', identifier: ident });
            } else {
                massResults.push({
                    source: 'hacienda',
                    identificacion: ident,
                    type: type,
                    success: false,
                    error: (response && response.error) || 'Sin resultados para esta identificación.',
                });
                failures += 1;
                usageEntries.push({ type: 'hacienda_error', identifier: ident });
            }
        } catch (error) {
            massResults.push({
                source: 'hacienda',
                identificacion: ident,
                type: type,
                success: false,
                error: error.message || 'Error de red al consultar Hacienda.',
            });
            failures += 1;
            usageEntries.push({ type: 'hacienda_error', identifier: ident });
        }
    }

    if (usageEntries.length) {
        logUsage(usageEntries);
    }

    if (massSubmitBtn && massSubmitBtn.dataset.originalText) {
        massSubmitBtn.textContent = massSubmitBtn.dataset.originalText;
        massSubmitBtn.disabled = false;
    } else if (massSubmitBtn) {
        massSubmitBtn.disabled = false;
    }
    if (massSpinner) {
        massSpinner.classList.add('hidden');
    }
    if (massStatusText) {
        massStatusText.textContent = 'Consulta finalizada (' + success + ' éxito(s), ' + failures + ' error(es)).';
    }

    currentResultSource = 'hacienda';

    if (massResults.length) {
        displayMassResult(0);
    } else {
        updateMassControls();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.slide');
    let currentSlide = 0;

    if (slides.length > 0) {
        slides[0].classList.add('active');

        setInterval(function() {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }, 5000);
    }

    haciendaForm = document.getElementById('hacienda-form');
    submitButton = document.getElementById('hacienda-submit');
    haciendaModal = document.getElementById('hacienda-modal');
    modalTitle = haciendaModal ? haciendaModal.querySelector('#hacienda-modal-title') : null;
    modalBody = haciendaModal ? haciendaModal.querySelector('.hacienda-modal__body') : null;
    const modalCloseTriggers = haciendaModal ? haciendaModal.querySelectorAll('.hacienda-modal__close, .hacienda-modal__close-btn') : [];
    copyActivitiesBtn = haciendaModal ? haciendaModal.querySelector('.hacienda-copy-actividades') : null;
    copyAllBtn = haciendaModal ? haciendaModal.querySelector('.hacienda-copy-completa') : null;
    typeSelect = haciendaForm ? haciendaForm.querySelector('select[name="HaciendaSearchForm[type]"]') : null;
    identificacionInput = haciendaForm ? haciendaForm.querySelector('input[name="HaciendaSearchForm[identificacion]"]') : null;
    toggleMassBtn = document.getElementById('toggle-mass-query');
    massPanel = document.getElementById('mass-query-panel');
    massInput = document.getElementById('mass-query-input');
    massSubmitBtn = document.getElementById('mass-query-submit');
    massStatusEl = document.getElementById('mass-query-status');
    massSpinner = document.getElementById('mass-query-spinner');
    massStatusText = document.getElementById('mass-query-status-text');
    massPrevBtn = document.getElementById('mass-prev');
    massNextBtn = document.getElementById('mass-next');
    massIndicator = document.getElementById('mass-indicator');
    massExportBtn = document.getElementById('mass-export');

    defaultTypeValue = typeSelect ? typeSelect.value : 'fisica';
    massResults = [];
    currentMassIndex = -1;

    if (haciendaForm) {
        haciendaForm.addEventListener('submit', function (event) {
            event.preventDefault();
        });
    }

    if (typeSelect && identificacionInput) {
        identificacionInput.addEventListener('input', function () {
            const value = identificacionInput.value.replace(/\D+/g, '');
            if (value.startsWith('3101')) {
                typeSelect.value = 'juridica';
            }
        });

        typeSelect.addEventListener('change', function () {
            defaultTypeValue = typeSelect.value;
        });
    }

    const xmlViewerButton = document.getElementById('xml-viewer-button');
    const xmlUploadInput = document.getElementById('xml-upload');
    const dollarButton = document.getElementById('dollar-exchange-button');

    if (xmlViewerButton && xmlUploadInput) {
        xmlViewerButton.addEventListener('click', function (event) {
            event.preventDefault();
            xmlUploadInput.click();
        });

        xmlUploadInput.addEventListener('change', function (event) {
            const files = Array.from(event.target.files || []);
            if (!files.length) {
                return;
            }
            loadXmlInvoices(files);
            xmlUploadInput.value = '';
        });
    }

    if (dollarButton) {
        dollarButton.addEventListener('click', function (event) {
            event.preventDefault();
            loadDollarData();
        });
    }

    if (toggleMassBtn && massPanel) {
        toggleMassBtn.addEventListener('click', function () {
            massPanel.classList.toggle('hidden');
            if (massPanel.classList.contains('hidden')) {
                if (massStatusText) {
                    massStatusText.textContent = '';
                }
                if (massSpinner) {
                    massSpinner.classList.add('hidden');
                }
            }
        });
    }

    if (haciendaModal) {
        modalCloseTriggers.forEach(function (button) {
            button.addEventListener('click', function () {
                closeModal(haciendaModal);
            });
        });

        haciendaModal.addEventListener('click', function (event) {
            if (event.target === haciendaModal) {
                closeModal(haciendaModal);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !haciendaModal.classList.contains('hidden')) {
                closeModal(haciendaModal);
            }
        });
    }

    if (copyActivitiesBtn) {
        copyActivitiesBtn.addEventListener('click', function () {
            const list = modalBody ? modalBody.querySelector('.hacienda-actividades-list') : null;
            if (!list) {
                copyTextToClipboard('No hay actividades registradas.');
                return;
            }
            const items = Array.from(list.querySelectorAll('li'));
            const codigos = items.map(function (item) {
                return item.getAttribute('data-codigo') || item.textContent;
            }).filter(Boolean);
            copyTextToClipboard(codigos.join('\n')).catch(function (err) {
                console.error('Error al copiar actividades:', err);
            });
        });
    }

    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', function () {
            const text = buildCopyTextFromModal();
            copyTextToClipboard(text).catch(function (err) {
                console.error('Error al copiar información:', err);
            });
        });
    }

    if (massExportBtn) {
        massExportBtn.addEventListener('click', function () {
            exportMassResultsToExcel();
        });
    }

    if (submitButton && haciendaForm && haciendaModal && modalTitle && modalBody) {
        submitButton.addEventListener('click', function () {
            const rawIdent = identificacionInput ? identificacionInput.value.trim() : '';

            if (!rawIdent) {
                openModal(haciendaModal, modalTitle, modalBody, '<p>Por favor ingrese un número de identificación válido.</p>', true);
                return;
            }

            const typeValue = typeSelect ? typeSelect.value : defaultTypeValue;

            submitButton.classList.add('is-loading');
            submitButton.disabled = true;
            submitButton.dataset.originalText = submitButton.dataset.originalText || submitButton.textContent;
            submitButton.textContent = 'Consultando...';

            requestHaciendaRecord(typeValue, rawIdent)
                .then(function (response) {
                    submitButton.classList.remove('is-loading');
                    submitButton.disabled = false;
                    if (submitButton.dataset.originalText) {
                        submitButton.textContent = submitButton.dataset.originalText;
                    }
                    showSingleResult(response, typeValue, rawIdent);
                })
                .catch(function (error) {
                    submitButton.classList.remove('is-loading');
                    submitButton.disabled = false;
                    if (submitButton.dataset.originalText) {
                        submitButton.textContent = submitButton.dataset.originalText;
                    }
                    clearMassState();
                    openModal(haciendaModal, modalTitle, modalBody, '<p>' + escapeHtml(error.message || 'No se pudo contactar el servicio en este momento. Inténtalo nuevamente más tarde.') + '</p>', true);
                });
        });
    }

    if (massSubmitBtn && massInput) {
        massSubmitBtn.addEventListener('click', function () {
            const raw = massInput.value || '';
            const identifiers = raw
                .split(/\r?\n|[,;\s]+/)
                .map(function (item) { return item.trim(); })
                .filter(function (item, index, array) {
                    return item && array.indexOf(item) === index;
                });
            runMassQuery(identifiers);
        });
    }

    if (massPrevBtn) {
        massPrevBtn.addEventListener('click', function () {
            if (currentMassIndex > 0) {
                displayMassResult(currentMassIndex - 1);
            }
        });
    }

    if (massNextBtn) {
        massNextBtn.addEventListener('click', function () {
            if (currentMassIndex < massResults.length - 1) {
                displayMassResult(currentMassIndex + 1);
            }
        });
    }
});

