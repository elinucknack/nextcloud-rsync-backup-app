OC.RsyncBackup = {
    
    params: {},
    
    loadParams: () => OC.RsyncBackup.params = JSON.parse($('#app-params-rsync').val()),
    
    navigationSelector: '#app-navigation-rsync .app-navigation-list',
    
    contentSelector: '#app-content-rsync',
    
    onElementPresent: (selector, callback) => {
        (selector => new Promise(resolve => {
            if (document.querySelector(selector)) {
                return resolve(document.querySelector(selector));
            }

            const observer = new MutationObserver(() => {
                if (document.querySelector(selector)) {
                    observer.disconnect();
                    resolve(document.querySelector(selector));
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }))(selector).then(element => {
            callback(element);
        });
    },
    
    onNavigationPresent: callback => OC.RsyncBackup.onElementPresent(OC.RsyncBackup.navigationSelector, callback),
    
    onContentPresent: callback => OC.RsyncBackup.onElementPresent(OC.RsyncBackup.contentSelector, callback),
    
    createLayout: appName => {
        $(`<div class="${window.innerWidth <= 1024 ? 'close' : ''}" id="app-navigation-rsync"></div>`).append(
            $('<ul class="app-navigation-list"></ul>').append(
                $('<li class="app-navigation-entry"></li>').append(
                    $('<span class="app-navigation-entry-link"></span>').append(
                        $('<div class="app-navigation-entry-icon icon-loading-small"></div>'),
                        $('<span class="app-navigation-entry-title"></span>').text(t(appName, 'Loading...'))
                    )
                )
            ),
            $('<button class="app-navigation-toggle" id="app-navigation-toggle-open"></button>')
                .attr('title', t(appName, 'Open navigation'))
                .append('<svg fill="currentColor" width="20" height="20" viewBox="0 0 24 24"><path d="M3,6H21V8H3V6M3,11H21V13H3V11M3,16H21V18H3V16Z"></path></svg>')
                .on('click', () => $('#app-navigation-rsync').removeClass('close')),
            $('<button class="app-navigation-toggle" id="app-navigation-toggle-close"></button>')
                .attr('title', t(appName, 'Close navigation'))
                .append('<svg fill="currentColor" width="20" height="20" viewBox="0 0 24 24"><path d="M21,15.61L19.59,17L14.58,12L19.59,7L21,8.39L17.44,12L21,15.61M3,6H16V8H3V6M3,13V11H13V13H3M3,18V16H16V18H3Z"></path></svg>')
                .on('click', () => $('#app-navigation-rsync').addClass('close'))
        ).appendTo($('#content'));
        $('<div id="app-content-rsync"></div>').append(
            $('<h2 class="icon-loading-small"></h2>').text(t(appName, 'Loading...'))
        ).appendTo($('#content'));

        let windowWidth = window.innerWidth;
        $(window).on('resize', () => {
            if (window.innerWidth <= 1024 && windowWidth > 1024) {
                $('#app-navigation-rsync').addClass('close');
            }
            if (window.innerWidth > 1024 && windowWidth <= 1024) {
                $('#app-navigation-rsync').removeClass('close');
            }
            windowWidth = window.innerWidth;
        });
    },
    
    loadNavigation: (appName, element) => {
        $.ajax({
            url: OC.generateUrl(`/apps/${appName}/action/get-navigation`),
            type: 'POST',
            contentType: 'application/json'
        }).done(response => {
            $(element).empty();
            
            if (response.type === 'success') {
                for (let item of response.data) {
                    $(`<li class="app-navigation-entry${window.location.pathname === item.url ? ' active' : ''}"></li>`).append(
                        $(window.location.pathname === item.url ? '<span></span>' : '<a></a>')
                            .addClass('app-navigation-entry-link')
                            .attr('href', window.location.pathname === item.url ? '' : item.url)
                            .attr('title', t(appName, item.label))
                            .append(
                                $(`<div class="app-navigation-entry-icon ${item.icon}"></div>`),
                                $('<span class="app-navigation-entry-title"></span>').text(t(appName, item.label))
                            )
                    ).appendTo($(element));
                }
            } else {
                OC.closeButton.alert(response.message, t(appName, 'Error'));
            }
        }).fail(response => {
            $(element).empty();
            
            alert(t(appName, 'Error') + ': ' + t(appName, response.status === 0 ? 'Connection lost' : response.statusText));
        });
    },
    
    createForm: params => {
        let id = params.id || null;
        let appName = params.appName || null;
        let dataSource = params.dataSource || null;
        let mapSource = params.mapSource || (data => data);
        let render = params.render || (() => {});
        let refreshInterval = params.refreshInterval || null;
        
        let model = {};
        let inputs = [];
        let notificationDivs = [];
        
        let formDisabled = false;
        
        let evaluateConf = () => {
            for (let input of inputs) {
                let element = $('#' + (id + '.' + input.attrPath).replaceAll('.', '\\.'));
                let disabled = input.disabled(model) || formDisabled;
                if (input.type === 'textArray') {
                    element.find('input, button').prop('disabled', disabled);
                } if (!['header', 'attrTable', 'messageLog'].includes(input.type)) {
                    element.prop('disabled', disabled);
                }
                let hidden = input.hidden(model);
                if (['header', 'attrTable', 'messageLog', 'button'].includes(input.type)) {
                    if (hidden) {
                        element.hide();
                    } else {
                        element.show();
                    }
                } else {
                    if (hidden) {
                        element.parent().hide();
                    } else {
                        element.parent().show();
                    }
                }
                if (input.type === 'select') {
                    let value = element.val();
                    element.empty();
                    let options = input.options();
                    for (let optionKey in options) {
                        $('<option></option>')
                            .attr('value', optionKey)
                            .text(t(appName, options[optionKey]))
                            .appendTo(element);
                    }
                    element.val(value);
                }
            }
        };
        
        let pushValues = () => {
            for (let input of inputs) {
                if (input.type !== 'button') {
                    let value = model;
                    for (let attrSubPath of input.attrPath.split('.')) {
                        value = value[attrSubPath];
                    }
                    let element = $('#' + (id + '.' + input.attrPath).replaceAll('.', '\\.'));
                    if (input.type === 'header') {
                        element.find('.label').text(t(appName, input.text, input.params(model)));
                        if (input.closeUrl === null) {
                            element.off('click');
                            element.find('.button.close').hide();
                        } else {
                            element.on('click', () => window.open(OC.generateUrl(input.closeUrl), '_self'));
                            element.find('.button.close').show();
                        }
                    } else if (input.type === 'checkbox') {
                        element.prop('checked', value);
                    } else if (input.type === 'textArray') {
                        element.empty();
                        let disabled = input.disabled();
                        let setProperty = () => {
                            setModelProperty(input.attrPath, element.find('input').map(function() {
                                return $(this).val();
                            }).get());
                        };
                        let createItem = itemValue => {
                            let item = $('<div class="rsync-item"></div>');
                            return item.append(
                                $('<input type="text" />').prop('disabled', disabled).on('change', () => setProperty()).val(itemValue),
                                $('<button class="remove icon-delete"></button>').on('click', () => {
                                    item.remove();
                                    setProperty();
                                })
                            );
                        };
                        value.forEach(itemValue => {
                            element.append(createItem(itemValue));
                        });
                        let buttonAdd = $('<button class="add icon-add"></button>');
                        element.append(buttonAdd.on('click', () => {
                            createItem(null).insertBefore(buttonAdd);
                            setProperty();
                        }));
                    } else if (input.type === 'attrTable') {
                        input.rows.forEach(row => {
                            let subElement = $('#' + (id + '.' + input.attrPath + '.' + row.attr).replaceAll('.', '\\.'));
                            let subValue = value;
                            for (let subAttr of row.attr.split('.')) {
                                subValue = subValue[subAttr];
                            }
                            subElement.text(subValue);
                            if (row.class !== undefined) {
                                let classSubValue = value;
                                for (let subClass of row.class.split('.')) {
                                    classSubValue = classSubValue[subClass];
                                }
                                subElement.prop('class', classSubValue);
                            }
                        });
                    } else if (input.type === 'messageLog') {
                        element.empty();
                        if (value.length > 0) {
                            for (let message of value) {
                                $('<span></span>').addClass(input.mapMessageClass(message)).text(input.mapMessage(message)).appendTo(element);
                            }
                        } else {
                            $('<span class="no-data"></span>').text(t('rsync_backup', 'No data')).appendTo(element);
                        }
                    } else {
                        element.val(value);
                    }
                }
            }
        };
        
        let setModelProperty = (attrPath, value) => {
            let splittedPath = attrPath.split('.');
            let node = model;
            let i = 0;
            while (i < splittedPath.length - 1) {
                node = node[splittedPath[i]];
                i++;
            }
            node[splittedPath[i]] = value;
            evaluateConf();
        };
        
        let clearNotification = () => {
            notificationDivs.forEach(attrPath => {
                $('#' + (id + '.' + attrPath).replaceAll('.', '\\.')).empty();
            });
        };
        
        let showNotification = message => {
            notificationDivs.forEach(attrPath => {
                $('#' + (id + '.' + attrPath).replaceAll('.', '\\.')).text(message);
            });
        };
        
        let refresh = () => {
            $.ajax({
                url: OC.generateUrl(dataSource),
                type: 'POST',
                contentType: 'application/json'
            }).done(response => {
                if (response.type === 'success') {
                    model = mapSource(response.data);
                    evaluateConf();
                    pushValues();
                    clearNotification();
                } else {
                    showNotification(t(appName, 'Error') + ': ' + response.message);
                }
                timeout = setTimeout(() => refresh(), refreshInterval);
            }).fail(response => {
                showNotification(t(appName, 'Error') + ': ' + t(appName, response.status === 0 ? 'Connection lost' : response.statusText));
                timeout = setTimeout(() => refresh(), refreshInterval);
            });
        };
        
        return {
            
            createSection: sectionClass => $(`<section class="${sectionClass}"></section>`),
            
            init: () => {
                if (dataSource !== null) {
                    $.ajax({
                        url: OC.generateUrl(dataSource),
                        type: 'POST',
                        contentType: 'application/json'
                    }).done(response => {
                        if (response.type === 'success') {
                            model = mapSource(response.data);
                            render();
                            evaluateConf();
                            pushValues();
                            clearNotification();
                        } else {
                            alert(t(appName, 'Error') + ': ' + response.message);
                        }
                        if (refreshInterval !== null) {
                            timeout = setTimeout(() => refresh(), refreshInterval);
                        }
                    }).fail(response => {
                        alert(t(appName, 'Error') + ': ' + t(appName, response.status === 0 ? 'Connection lost' : response.statusText));
                        if (refreshInterval !== null) {
                            timeout = setTimeout(() => refresh(), refreshInterval);
                        }
                    });
                } else {
                    model = mapSource({});
                }
            },
            
            createHeader: (attrPath, level, conf) => {
                inputs.push({
                    attrPath,
                    type: 'header',
                    disabled: (conf || {}).disabled || (data => false),
                    hidden: (conf || {}).hidden || (data => false),
                    text: (conf || {}).text || '',
                    params: (conf || {}).params || (data => {}),
                    closeUrl : (conf || {}).closeUrl || null
                });
                let header = $(`<h${level} id="${id}.${attrPath}"></h${level}>`);
                $('<span class="label"></span>').appendTo(header);
                $('<a class="button close" style="display: none;"></a>')
                    .attr('title', t('rsync_backup', 'Close'))
                    .append($('<span class="icon-close"></span>'))
                    .appendTo(header);
                return header;
            },
            
            createCheckbox: (attrPath, text, conf) => {
                inputs.push({
                    attrPath,
                    type: 'checkbox',
                    disabled: (conf || {}).disabled || (data => false),
                    hidden: (conf || {}).hidden || (data => false)
                });
                return $(`<label class="rsync-checkbox" for="${id}.${attrPath}"></label>`).append(
                    $(`<input type="checkbox" id="${id}.${attrPath}" disabled />`)
                        .on('change', event => setModelProperty(attrPath, $(event.currentTarget).is(':checked'))),
                    $('<span></span>').text(t(appName, text))
                ).hide();
            },
            
            createTextInput: (attrPath, text, conf) => {
                inputs.push({
                    attrPath,
                    type: 'text',
                    disabled: (conf || {}).disabled || (data => false),
                    hidden: (conf || {}).hidden || (data => false)
                });
                return $(`<label class="rsync-text" for="${id}.${attrPath}"></label>`).append(
                    $('<span></span>').text(t(appName, text)),
                    $(`<input type="text" id="${id}.${attrPath}" disabled />`)
                        .on('change', event => setModelProperty(attrPath, $(event.currentTarget).val()))
                ).hide();
            },
            
            createPasswordInput: (attrPath, text, conf) => {
                inputs.push({
                    attrPath,
                    type: 'password',
                    disabled: (conf || {}).disabled || (data => false),
                    hidden: (conf || {}).hidden || (data => false)
                });
                return $(`<label class="rsync-password" for="${id}.${attrPath}"></label>`).append(
                    $('<span></span>').text(t(appName, text)),
                    $(`<input type="password" id="${id}.${attrPath}" disabled />`)
                        .on('change', event => setModelProperty(attrPath, $(event.currentTarget).val()))
                ).hide();
            },
            
            createSelect: (attrPath, text, conf) => {
                inputs.push({
                    attrPath,
                    type: 'select',
                    disabled: (conf || {}).disabled || (data => false),
                    hidden: (conf || {}).hidden || (data => false),
                    options: (conf || {}).options || (data => {})
                });
                return $(`<label class="rsync-select" for="${id}.${attrPath}"></label>`).append(
                    $('<span></span>').text(t(appName, text)),
                    $(`<select id="${id}.${attrPath}" disabled></select>`)
                        .on('change', event => setModelProperty(attrPath, $(event.currentTarget).val()))
                ).hide();
            },
            
            createTextInputArray: (attrPath, text, conf) => {
                inputs.push({
                    attrPath,
                    type: 'textArray',
                    disabled: (conf || {}).disabled || (data => false),
                    hidden: (conf || {}).hidden || (data => false)
                });
                return $(`<label class="rsync-text-array" for="${id}.${attrPath}"></label>`).append(
                    $('<span></span>').text(t(appName, text)),
                    $(`<div id="${id}.${attrPath}"></div>`).text(t(appName, text))
                ).hide();
            },
            
            createAttrTable: (attrPath, conf) => {
                inputs.push({
                    attrPath,
                    type: 'attrTable',
                    disabled: (conf || {}).disabled || (data => false),
                    hidden: (conf || {}).hidden || (data => false),
                    rows: (conf || {}).rows || {}
                });
                let rows = (conf || {}).rows || [];
                let table = $(`<table class="rsync-attr-table" id="${id}.${attrPath}"></table>`);
                let tbody = $('<tbody></tbody>').appendTo(table);
                rows.forEach(row => {
                    $(`<tr><th>${t(appName, row.label)}</th><td id="${id}.${attrPath}.${row.attr}"></td></tr>`).appendTo(tbody);
                });
                return table;
            },
            
            createMessageLog: (attrPath, conf) => {
                inputs.push({
                    attrPath,
                    type: 'messageLog',
                    disabled: (conf || {}).disabled || (data => false),
                    hidden: (conf || {}).hidden || (data => false),
                    mapMessage: (conf || {}).mapMessage || (message => ''),
                    mapMessageClass: (conf || {}).mapMessageClass || (message => '')
                });
                return $(`<div class="rsync-message-log" id="${id}.${attrPath}"></div>`);
            },
            
            createSubmitButton: (attrPath, text, conf) => {
                inputs.push({
                    attrPath,
                    type: 'button',
                    disabled: (conf || {}).disabled || (data => false),
                    hidden: (conf || {}).hidden || (data => false)
                });
                return $(`<button class="rsync-sumbit" id="${id}.${attrPath}" disabled></button>`).text(t(appName, text)).on('click', () => {
                    formDisabled = true;
                    evaluateConf();
                    $.ajax({
                        url: OC.generateUrl((conf || {}).dataTarget || null),
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(((conf || {}).mapTarget || (data => {}))(model))
                    }).done(response => {
                        formDisabled = false;
                        evaluateConf();
                        if (response.type === 'success') {
                            alert(t(appName, 'Notification') + ': ' + response.message);
                        } else {
                            alert(t(appName, 'Error') + ': ' + response.message);
                        }
                    }).fail(response => {
                        formDisabled = false;
                        evaluateConf();
                        alert(t(appName, 'Error') + ': ' + t(appName, response.status === 0 ? 'Connection lost' : response.statusText));
                    });
                }).hide();
            },
            
            createNotificationDiv: attrPath => {
                notificationDivs.push(attrPath);
                return $(`<div class="rsync-notification-div" id="${id}.${attrPath}"></div>`);
            }
            
        };
    },
    
    createTable: params => {
        let id = params.id || null;
        let appName = params.appName || null;
        let container = params.container || null;
        let title = params.title || null;
        let dataSource = params.dataSource || null;
        let mapSource = params.mapSource || (data => data);
        let columns = params.columns || [];
        let row = params.row || {};
        
        let label = $('<span class="label"></span>');
        let notificationsDiv = $(`<div class="${appName}-notifications"></div>`);
        let tableBody = $('<tbody></tbody>');
        let data = null;
        let pageSize = params.pageSize || 20;
        let refreshInterval = params.refreshInterval || 5000;
        
        let ajax = null;
        let timeout = null;
        let pageSwitchLock = false;
        
        let lsGetPage = () => {
            return parseInt(localStorage.getItem(`${id}.page`) || 0);
        };

        let lsSetPage = (page) => {
            localStorage.setItem(`${id}.page`, page);
        };

        let abortRefresh = () => {
            if (ajax !== null) {
                ajax.abort();
            }
            if (timeout !== null) {
                clearTimeout(timeout);
            }
        };

        let prevPage = () => {
            if (data !== null && !pageSwitchLock) {
                if (lsGetPage() <= 0) {
                    return;
                }
                abortRefresh();
                lsSetPage(lsGetPage() - 1);
                show(true);
            }
        };

        let setPage = (pg) => {
            if (data !== null && pg !== null && !pageSwitchLock) {
                if (pg.match(/^[0-9]+$/g) === null) {
                    alert(t(appName, 'Not a number') + '!');
                    return;
                }
                let parsedPg = parseInt(pg);
                if (parsedPg <= 0 || parsedPg > Math.max(Math.ceil(data.count / pageSize), 1)) {
                    alert(t(appName, 'Out of range', {
                        maxPage: Math.max(Math.ceil(data.count / pageSize), 1)
                    }) + '!');
                    return;
                }
                abortRefresh();
                lsSetPage(parsedPg - 1);
                show(true);
            }
        };

        let nextPage = () => {
            if (data !== null && !pageSwitchLock) {
                if (lsGetPage() >= Math.max(Math.ceil(data.count / pageSize), 1) - 1) {
                    return;
                }
                abortRefresh();
                lsSetPage(lsGetPage() + 1);
                show(true);
            }
        };

        let dataAreEqual = (dataA, dataB) => {
            if (dataA === null && dataB === null) {
                return true;
            }
            if (dataA === null || dataB === null || dataA.count !== dataB.count) {
                return false;
            }
            for (let i in dataA.table) {
                if (dataA.table[i].id !== dataB.table[i].id || dataA.table[i].updated !== dataB.table[i].updated) {
                    return false;
                }
            }
            return true;
        };
        
        let show = noDataCheck => {
            pageSwitchLock = true;
            ajax = $.ajax({
                url: OC.generateUrl(dataSource),
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({page: lsGetPage(), pageSize})
            }).done(response => {
                notificationsDiv.empty();
                if (response.type === 'success') {
                    if (data === null) {
                        container.empty();

                        $('<h2></h2>').append(
                            label,
                            $('<a class="button prev"></a>')
                                .attr('title', t(appName, 'Previous page'))
                                .on('click', () => prevPage())
                                .append($('<span class="icon-view-previous"></span>')),
                            $('<a class="button page"></a>')
                                .attr('title', t(appName, 'Set page'))
                                .on('click', () => setPage(prompt(t(appName, 'Page', {
                                    maxPage: Math.max(Math.ceil(data.count / pageSize), 1)
                                }) + ':', lsGetPage() + 1)))
                                .append($('<span class="icon-projects"></span>')),
                            $('<a class="button next"></a>')
                                .attr('title', t(appName, 'Next page'))
                                .on('click', () => nextPage())
                                .append($('<span class="icon-view-next"></span>'))
                        ).appendTo(container);

                        let tr = $('<tr></tr>');
                        columns.forEach(column => {
                            $('<th></th>').text(t(appName, column.label)).appendTo(tr);
                        });

                        $(`<section class="${appName}-data"></section>`).append(
                            notificationsDiv,
                            $(`<table class="${appName}-table"></table>`).append(
                                $('<thead></thead>').append(tr),
                                tableBody
                            )
                        ).appendTo(container);
                    }

                    let newData = mapSource(response.data);
                    if (noDataCheck || !dataAreEqual(data, newData)) {
                        tableBody.empty();
                        data = newData;

                        if (data.count > 0) {
                            label.text(t(appName, `${title} ({page})`, {page: (lsGetPage() + 1) + '/' + Math.max(Math.ceil(data.count / pageSize), 1)}));
                            for (let i in data.table) {
                                let tr = $('<tr></tr>');
                                columns.forEach(column => {
                                    let td = $('<td></td>').text(data.table[i][column.attr]);
                                    if (data.table[i][column.class] !== undefined) {
                                        td.addClass(data.table[i][column.class]);
                                    }
                                    td.appendTo(tr);
                                });
                                if (row.link !== undefined) {
                                    tr.attr('title', t(appName, row.link.title)).on('click', () => window.open(OC.generateUrl(row.link.url(data.table[i])), '_self'));
                                }
                                tr.appendTo(tableBody);
                            }
                        } else {
                            $('<tr class="no-data"></tr>').append(
                                $(`<td colspan="${columns.length}"></td>`).text(t(appName, 'No data')),
                            ).appendTo(tableBody);
                        }
                    }
                } else {
                    if (data === null) {
                        container.empty();

                        alert(t(appName, 'Error') + ': ' + response.message);
                    } else {
                        $('<p class="error"></p>').text(t(appName, 'Error') + ': ' + response.message).appendTo(notificationsDiv);
                    }
                }
                pageSwitchLock = false;

                timeout = setTimeout(() => show(false), refreshInterval);
            }).fail(response => {
                notificationsDiv.empty();
                if (data === null) {
                    container.empty();

                    alert(t(appName, 'Error') + ': ' + t(appName, response.status === 0 ? 'Connection lost' : response.statusText));
                } else {
                    $(`<p class="${appName}-error-notification"></p>`).text(t(appName, 'Error') + ': ' + t(appName, response.status === 0 ? 'Connection lost' : response.statusText)).appendTo(notificationsDiv);
                }
                pageSwitchLock = false;

                timeout = setTimeout(() => show(false), refreshInterval);
            });
        };
        
        return {
            
            init: () => show()
            
        };
    }
    
};
