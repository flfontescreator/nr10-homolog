document.addEventListener('DOMContentLoaded', function () {
    // ---------- Sidebar: recolher/expandir (desktop) com preferência salva ----------
    var body = document.body;
    var isMobile = function () {
        return window.matchMedia('(max-width: 900px)').matches;
    };

    var localStorageEnabled = function () {
        try {
            localStorage.setItem('__nr10_test__', '1');
            localStorage.removeItem('__nr10_test__');
            return true;
        } catch (e) {
            return false;
        }
    };

    var applyCollapsed = function (collapsed) {
        body.classList.toggle('sidebar-collapsed', collapsed);
        if (localStorageEnabled()) {
            localStorage.setItem('nr10_sidebar_collapsed', collapsed ? '1' : '0');
        }
    };

    if (localStorageEnabled() && localStorage.getItem('nr10_sidebar_collapsed') === '1') {
        body.classList.add('sidebar-collapsed');
    }

    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (isMobile()) {
                body.classList.toggle('sidebar-open');
            } else {
                applyCollapsed(!body.classList.contains('sidebar-collapsed'));
            }
        });
    });

    document.querySelectorAll('[data-sidebar-expand]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyCollapsed(!body.classList.contains('sidebar-collapsed'));
        });
    });

    document.querySelectorAll('[data-sidebar-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            body.classList.remove('sidebar-open');
        });
    });

    document.querySelectorAll('.sidebar-link').forEach(function (link) {
        link.addEventListener('click', function () {
            body.classList.remove('sidebar-open');
        });
    });

    // ---------- Senha: mostrar/ocultar com ícones SVG ----------
    var eyeIcons = {
        on: '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        off: '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m1 1 22 22"/></svg>'
    };

    document.querySelectorAll('.password-toggle').forEach(function (btn) {
        btn.innerHTML = btn.dataset.show === '1' ? eyeIcons.off : eyeIcons.on;
        btn.addEventListener('click', function () {
            var input = this.parentElement.querySelector('input');
            var show = this.dataset.show === '1';
            input.type = show ? 'password' : 'text';
            this.dataset.show = show ? '0' : '1';
            this.innerHTML = show ? eyeIcons.on : eyeIcons.off;
        });
    });

    // ---------- Confirmação de ações destrutivas ----------
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // ---------- Cadastro de cliente: busca de CNPJ (BrasilAPI) ----------
    var loadCidades = null;
    var loadBairros = null;

    var digitsOnly = function (value) {
        return (value || '').replace(/\D/g, '');
    };

    var formatCep = function (value) {
        var digits = digitsOnly(value);
        return digits.length === 8 ? digits.slice(0, 5) + '-' + digits.slice(5) : value;
    };

    var setAddressField = function (id, value) {
        var field = document.getElementById(id);
        if (field && value) {
            field.value = value;
        }
    };

    // Preenche o endereço do cliente a partir da Receita/ViaCEP: no cadastro
    // novo os campos são separados; sem eles, cai na string única antiga.
    var fillEndereco = function (data) {
        if (document.getElementById('numero')) {
            setAddressField('address', data.logradouro || '');
            setAddressField('numero', data.numero || '');
            setAddressField('complemento', data.complemento || '');
            setAddressField('bairro', data.bairro || '');
            setAddressField('cidade', data.municipio || '');
            setAddressField('uf', data.uf || '');
            setAddressField('cep', data.cep ? formatCep(data.cep) : '');
        } else {
            setAddressField('address', [
                data.logradouro,
                data.numero,
                data.bairro,
                data.municipio,
                data.uf
            ].filter(Boolean).join(', '));
        }

        if (typeof loadCidades === 'function') loadCidades();
        if (typeof loadBairros === 'function') loadBairros();
    };

    var cnpjInput = document.getElementById('cnpj');
    var buscarBtn = document.getElementById('btn-buscar-cnpj');

    if (cnpjInput && buscarBtn) {
        var status = document.getElementById('cnpj-status');
        var nameInput = document.getElementById('name');

        var setStatus = function (message, isError) {
            if (!status) return;
            status.textContent = message || '';
            status.style.color = isError ? 'var(--danger)' : 'var(--muted)';
        };

        buscarBtn.addEventListener('click', function () {
            var cnpj = digitsOnly(cnpjInput.value);

            if (cnpj.length !== 14) {
                setStatus('Digite um CNPJ válido com 14 dígitos.', true);
                return;
            }

            setStatus('Consultando CNPJ…');

            fetch('https://brasilapi.com.br/api/cnpj/v1/' + cnpj)
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('CNPJ não encontrado ou inválido.');
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (nameInput) {
                        nameInput.value = data.razao_social || '';
                    }
                    fillEndereco(data);
                    setStatus('Dados do CNPJ preenchidos.');
                })
                .catch(function (error) {
                    setStatus(error.message || 'Falha ao consultar o CNPJ.', true);
                });
        });
    }

    // ---------- Cadastro de cliente: busca de CEP (ViaCEP) e autocompletar ----------
    var enderecoForm = document.querySelector('form[data-cep-url]');

    if (enderecoForm) {
        var cepInput = document.getElementById('cep');
        var buscarCepBtn = document.getElementById('btn-buscar-cep');
        var cepStatus = document.getElementById('cep-status');
        var ufSelect = document.getElementById('uf');
        var cidadeInput = document.getElementById('cidade');
        var bairroInput = document.getElementById('bairro');
        var cidadesList = document.getElementById('cidades-lista');
        var bairrosList = document.getElementById('bairros-lista');

        var setCepStatus = function (message, isError) {
            if (!cepStatus) return;
            cepStatus.textContent = message || '';
            cepStatus.style.color = isError ? 'var(--danger)' : 'var(--muted)';
        };

        var fillDatalist = function (list, nomes) {
            if (!list) return;
            list.textContent = '';
            (nomes || []).forEach(function (nome) {
                var option = document.createElement('option');
                option.value = nome;
                list.appendChild(option);
            });
        };

        loadCidades = function () {
            var uf = ufSelect ? ufSelect.value : '';

            if (!uf) {
                fillDatalist(cidadesList, []);
                return;
            }

            fetch(enderecoForm.dataset.cidadesUrl + '?uf=' + encodeURIComponent(uf))
                .then(function (response) { return response.json(); })
                .then(function (nomes) { fillDatalist(cidadesList, nomes); })
                .catch(function () { fillDatalist(cidadesList, []); });
        };

        loadBairros = function () {
            var uf = ufSelect ? ufSelect.value : '';
            var cidade = cidadeInput ? cidadeInput.value.trim() : '';

            if (!uf || !cidade) {
                fillDatalist(bairrosList, []);
                return;
            }

            fetch(enderecoForm.dataset.bairrosUrl
                    + '?uf=' + encodeURIComponent(uf)
                    + '&cidade=' + encodeURIComponent(cidade))
                .then(function (response) { return response.json(); })
                .then(function (nomes) { fillDatalist(bairrosList, nomes); })
                .catch(function () { fillDatalist(bairrosList, []); });
        };

        if (ufSelect) {
            ufSelect.addEventListener('change', function () {
                if (cidadeInput) cidadeInput.value = '';
                if (bairroInput) bairroInput.value = '';
                fillDatalist(bairrosList, []);
                loadCidades();
            });
        }

        if (cidadeInput) {
            cidadeInput.addEventListener('change', function () {
                if (bairroInput) bairroInput.value = '';
                loadBairros();
            });
        }

        if (bairroInput) {
            bairroInput.addEventListener('focus', loadBairros);
        }

        if (cepInput && buscarCepBtn) {
            var buscarCep = function () {
                var cep = digitsOnly(cepInput.value);

                if (cep.length !== 8) {
                    setCepStatus('Digite um CEP válido com 8 dígitos.', true);
                    return;
                }

                setCepStatus('Consultando CEP…');

                fetch(enderecoForm.dataset.cepUrl.replace('__CEP__', cep))
                    .then(function (response) {
                        return response.json()
                            .catch(function () { return {}; })
                            .then(function (body) {
                                if (!response.ok) {
                                    throw new Error(body.message || 'CEP não encontrado.');
                                }
                                return body;
                            });
                    })
                    .then(function (data) {
                        cepInput.value = data.cep || cepInput.value;
                        setAddressField('address', data.address || '');
                        setAddressField('bairro', data.bairro || '');
                        setAddressField('cidade', data.cidade || '');
                        setAddressField('uf', data.uf || '');
                        loadCidades();
                        loadBairros();
                        setCepStatus('Endereço preenchido.');
                    })
                    .catch(function (error) {
                        setCepStatus(error.message || 'Falha ao consultar o CEP.', true);
                    });
            };

            buscarCepBtn.addEventListener('click', buscarCep);

            cepInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    buscarCep();
                }
            });
        }

        // Listas já na abertura, quando o cliente já tem endereço cadastrado.
        loadCidades();
        loadBairros();
    }

    // ---------- Picker de setores (multiplicar setores do cronograma) ----------
    var setoresList = document.getElementById('setores-list');
    var novoSetor = document.getElementById('novo-setor');
    var btnAddSetor = document.getElementById('btn-add-setor');

    var addSetorBadge = function (name) {
        if (!name || !setoresList) return;

        var alreadyAdded = false;
        setoresList.querySelectorAll('input[name="setores[]"]').forEach(function (input) {
            if (input.value === name) {
                alreadyAdded = true;
            }
        });
        if (alreadyAdded) {
            novoSetor.value = '';
            return;
        }

        var span = document.createElement('span');
        span.className = 'badge badge-setor';
        span.dataset.setor = name;

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'setores[]';
        input.value = name;
        span.appendChild(input);

        span.appendChild(document.createTextNode(name));

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'badge-remove';
        remove.dataset.removeSetor = '1';
        remove.setAttribute('aria-label', 'Remover setor');
        remove.innerHTML = '&times;';
        remove.tabIndex = -1;
        span.appendChild(remove);

        setoresList.appendChild(span);
        novoSetor.value = '';
    };

    if (setoresList && novoSetor && btnAddSetor) {
        btnAddSetor.addEventListener('click', function () {
            addSetorBadge(novoSetor.value);
            novoSetor.focus();
        });

        setoresList.addEventListener('click', function (event) {
            var removeBtn = event.target.closest('[data-remove-setor]');
            if (removeBtn) {
                removeBtn.closest('[data-setor]').remove();
            }
        });
    }
});