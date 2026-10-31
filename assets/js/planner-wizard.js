(function () {
    'use strict';

    const MONTHS_IT = [
        'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
        'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
    ];
    const WEEKDAYS_IT = ['L', 'M', 'M', 'G', 'V', 'S', 'D'];

    const VISITOR_COUNTRIES = [
        { id: 'es', name: 'Spain', flag: 'es' },
        { id: 'gb', name: 'United Kingdom', flag: 'gb' },
        { id: 'de', name: 'Germany', flag: 'de' },
        { id: 'fr', name: 'France', flag: 'fr' },
        { id: 'pt', name: 'Portugal', flag: 'pt' },
        { id: 'it', name: 'Italy', flag: 'it' },
    ];

    function init() {
        const cfg = window.opencomunePlanner;
        const landingEl = document.getElementById('oc-planner-landing');
        const wizardRoot = document.getElementById('oc-planner-wizard');
        if (!cfg || !landingEl || !wizardRoot) {
            return;
        }

        function showError(msg) {
            if (window.opencomunePlannerAlert) {
                if (msg) {
                    window.opencomunePlannerAlert.show(msg, 'error');
                } else {
                    window.opencomunePlannerAlert.clear();
                }
                return;
            }
        }

        if (window.opencomunePlannerAlert && cfg.i18n && cfg.i18n.dismissAlert) {
            window.opencomunePlannerAlert.setDismissLabel(cfg.i18n.dismissAlert);
        }
        const landingCityInput = document.getElementById('oc-planner-landing-city');
        const landingCitySuggestions = document.getElementById('oc-planner-landing-city-suggestions');
        const landingStartBtn = document.getElementById('oc-planner-landing-start');
        const originEl = document.getElementById('oc-planner-origin');
        const originGrid = document.getElementById('oc-planner-origin-grid');
        const travelersEl = document.getElementById('oc-planner-travelers');
        const categoriesEl = document.getElementById('oc-planner-categories');
        const continueBtn = document.getElementById('oc-planner-continue');
        const generateBtn = document.getElementById('oc-planner-generate');
        const calendarEl = document.getElementById('oc-planner-calendar');
        const startDateInput = document.getElementById('oc-planner-start-date');
        const endDateInput = document.getElementById('oc-planner-end-date');
        const rangeStartEl = document.getElementById('oc-planner-range-start');
        const rangeEndEl = document.getElementById('oc-planner-range-end');
        const startTimeInput = document.getElementById('oc-planner-start-time');
        const startInput = document.getElementById('oc-planner-start-input');

        let config = null;
        let cityItems = Array.isArray(cfg.landingCities) ? cfg.landingCities.slice() : [];
        let landingCitySelection = null;
        let city = wizardRoot.dataset.initialCity || '';
        let traveler = '';
        let visitorOrigin = '';
        const categories = new Set();
        let lat = null;
        let lng = null;
        let startLocationLabel = '';
        let startAutocomplete = null;

        let calYear = new Date().getFullYear();
        let calMonth = new Date().getMonth();
        let rangeStart = null;
        let rangeEnd = null;

        function restHeaders() {
            return {
                'Content-Type': 'application/json',
                'X-WP-Nonce': cfg.nonce,
            };
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/"/g, '&quot;');
        }

        function formatDisplayDate(iso) {
            if (!iso) {
                return '—';
            }
            const parts = iso.split('-');
            if (parts.length !== 3) {
                return iso;
            }
            return parts[2] + '/' + parts[1] + '/' + parts[0];
        }

        function toIso(y, m, d) {
            const mm = String(m + 1).padStart(2, '0');
            const dd = String(d).padStart(2, '0');
            return y + '-' + mm + '-' + dd;
        }

        function syncDateInputs() {
            const startIso = rangeStart ? toIso(rangeStart.y, rangeStart.m, rangeStart.d) : '';
            const endIso = rangeEnd ? toIso(rangeEnd.y, rangeEnd.m, rangeEnd.d) : startIso;
            if (startDateInput) {
                startDateInput.value = startIso;
            }
            if (endDateInput) {
                endDateInput.value = endIso || startIso;
            }
            if (rangeStartEl) {
                rangeStartEl.textContent = formatDisplayDate(startIso);
            }
            if (rangeEndEl) {
                rangeEndEl.textContent = formatDisplayDate(endIso || startIso);
            }
        }

        function dayInRange(y, m, d) {
            if (!rangeStart) {
                return false;
            }
            const t = new Date(y, m, d).getTime();
            const s = new Date(rangeStart.y, rangeStart.m, rangeStart.d).getTime();
            const e = rangeEnd
                ? new Date(rangeEnd.y, rangeEnd.m, rangeEnd.d).getTime()
                : s;
            return t >= s && t <= e;
        }

        function findCityItem(slug) {
            return cityItems.find(function (c) {
                return c.id === slug;
            });
        }

        function normalizeCitySearch(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        }

        function applyCityItem(item) {
            if (!item) {
                return;
            }
            city = item.id;
            landingCitySelection = item;
            if (landingCityInput) {
                landingCityInput.value = item.name;
            }
            applyStartFromCityItem(item);
        }

        function resolveCityFromInput(text) {
            const q = normalizeCitySearch(text);
            if (!q) {
                return null;
            }
            if (
                landingCitySelection &&
                normalizeCitySearch(landingCitySelection.name) === q
            ) {
                return landingCitySelection;
            }
            let match = cityItems.find(function (c) {
                return normalizeCitySearch(c.name) === q;
            });
            if (match) {
                return match;
            }
            match = cityItems.find(function (c) {
                return normalizeCitySearch(c.id).replace(/-/g, ' ') === q;
            });
            if (match) {
                return match;
            }
            const partial = cityItems.filter(function (c) {
                return normalizeCitySearch(c.name).includes(q);
            });
            if (partial.length === 1) {
                return partial[0];
            }
            const startsWith = cityItems.filter(function (c) {
                return normalizeCitySearch(c.name).startsWith(q);
            });
            if (startsWith.length === 1) {
                return startsWith[0];
            }
            return null;
        }

        function pickFirstLandingSuggestion() {
            if (!landingCitySuggestions || landingCitySuggestions.hidden) {
                return false;
            }
            const first = landingCitySuggestions.querySelector('.oc-planner-landing__suggestion');
            if (!first) {
                return false;
            }
            const id = first.getAttribute('data-city-id');
            const item = cityItems.find(function (c) {
                return c.id === id;
            });
            if (!item) {
                return false;
            }
            applyCityItem(item);
            closeLandingSuggestions();
            showError('');
            return true;
        }

        function closeLandingSuggestions() {
            if (!landingCitySuggestions) {
                return;
            }
            landingCitySuggestions.hidden = true;
            landingCitySuggestions.innerHTML = '';
            if (landingCityInput) {
                landingCityInput.setAttribute('aria-expanded', 'false');
            }
        }

        function renderLandingSuggestions(query) {
            if (!landingCitySuggestions || !landingCityInput) {
                return;
            }
            const q = normalizeCitySearch(query);
            const matches =
                q === ''
                    ? cityItems.slice(0, 12)
                    : cityItems
                          .filter(function (c) {
                              return normalizeCitySearch(c.name).includes(q);
                          })
                          .slice(0, 12);
            if (!matches.length) {
                closeLandingSuggestions();
                return;
            }
            landingCitySuggestions.innerHTML = matches
                .map(function (item) {
                    return (
                        '<li role="presentation"><button type="button" class="oc-planner-landing__suggestion" role="option" data-city-id="' +
                        escapeHtml(item.id) +
                        '">' +
                        escapeHtml(item.name) +
                        '</button></li>'
                    );
                })
                .join('');
            landingCitySuggestions.hidden = false;
            landingCityInput.setAttribute('aria-expanded', 'true');
            landingCitySuggestions.querySelectorAll('.oc-planner-landing__suggestion').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = btn.getAttribute('data-city-id');
                    const item = cityItems.find(function (c) {
                        return c.id === id;
                    });
                    if (item) {
                        applyCityItem(item);
                        closeLandingSuggestions();
                        showError('');
                    }
                });
            });
        }

        function bindLandingCityAutocomplete() {
            if (!landingCityInput) {
                return;
            }
            landingCityInput.addEventListener('focus', function () {
                renderLandingSuggestions(landingCityInput.value);
            });
            landingCityInput.addEventListener('input', function () {
                landingCitySelection = null;
                renderLandingSuggestions(landingCityInput.value);
            });
            landingCityInput.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape') {
                    closeLandingSuggestions();
                    return;
                }
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    if (pickFirstLandingSuggestion()) {
                        return;
                    }
                    const resolved = resolveCityFromInput(landingCityInput.value);
                    if (resolved) {
                        applyCityItem(resolved);
                        closeLandingSuggestions();
                        showError('');
                    }
                }
            });
        }

        document.addEventListener('click', function (ev) {
            if (!landingCityInput || !landingCitySuggestions) {
                return;
            }
            if (
                !landingCityInput.contains(ev.target) &&
                !landingCitySuggestions.contains(ev.target)
            ) {
                closeLandingSuggestions();
            }
        });

        bindLandingCityAutocomplete();
        if (cityItems.length === 1) {
            applyCityItem(cityItems[0]);
        } else if (city) {
            const preset = findCityItem(city);
            if (preset) {
                applyCityItem(preset);
            }
        }

        const headerBackBtn = document.getElementById('oc-planner-header-back');
        if (headerBackBtn) {
            headerBackBtn.addEventListener('click', function () {
                setStep('landing');
            });
        }

        window.opencomuneSyncPlannerHeaderHeight = function () {
            const compact = document.getElementById('oc-planner-app-header');
            const publicHeader = document.getElementById('site-header');
            let height = 0;
            if (
                document.body.classList.contains('oc-planner-wizard-flow') &&
                compact &&
                !compact.classList.contains('is-hidden')
            ) {
                height = compact.offsetHeight;
            } else if (publicHeader && publicHeader.offsetParent !== null) {
                height = publicHeader.offsetHeight;
            }
            document.documentElement.style.setProperty('--site-header-height', height + 'px');
        };

        window.addEventListener('resize', function () {
            if (typeof window.opencomuneSyncPlannerHeaderHeight === 'function') {
                window.opencomuneSyncPlannerHeaderHeight();
            }
        });

        if (document.body.classList.contains('oc-planner-wizard-flow')) {
            window.opencomuneSyncPlannerHeaderHeight();
        }

        function applyStartFromCityItem(item) {
            if (!item || !item.center) {
                if (config && config.defaultStart) {
                    lat = config.defaultStart.lat;
                    lng = config.defaultStart.lng;
                    startLocationLabel = config.defaultStart.address || '';
                }
                return;
            }
            lat = item.center.lat;
            lng = item.center.lng;
            startLocationLabel = item.center.address || item.name || '';
            if (startInput && startLocationLabel) {
                startInput.value = startLocationLabel;
            }
        }

        function syncPlannerFlowHeader(step) {
            const compact = document.getElementById('oc-planner-app-header');
            const backBtn = document.getElementById('oc-planner-header-back');

            document.body.classList.add('oc-planner-wizard-flow');

            if (compact) {
                compact.classList.remove('is-hidden');
                compact.setAttribute('aria-hidden', 'false');
            }

            if (backBtn) {
                backBtn.hidden = step === 'landing';
            }

            if (typeof window.opencomuneSyncPlannerHeaderHeight === 'function') {
                window.opencomuneSyncPlannerHeaderHeight();
            }
        }

        function setStep(step) {
            const showLanding = step === 'landing';
            const showWizard = step === 'wizard';
            const showOrigin = step === 'origin';

            landingEl.classList.toggle('is-hidden', !showLanding);
            wizardRoot.classList.toggle('is-hidden', !showWizard);
            if (originEl) {
                originEl.classList.toggle('is-hidden', !showOrigin);
            }
            syncPlannerFlowHeader(step);
            showError('');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function initStartAutocomplete() {
            if (!startInput || startAutocomplete || typeof google === 'undefined' || !google.maps?.places) {
                return;
            }
            startAutocomplete = new google.maps.places.Autocomplete(startInput, {
                fields: ['geometry', 'formatted_address', 'name'],
            });
            startAutocomplete.addListener('place_changed', function () {
                const place = startAutocomplete.getPlace();
                if (!place || !place.geometry || !place.geometry.location) {
                    return;
                }
                lat = place.geometry.location.lat();
                lng = place.geometry.location.lng();
                startLocationLabel =
                    (place.formatted_address || place.name || startInput.value || '').trim();
            });
        }

        function renderCalendar() {
            if (!calendarEl) {
                return;
            }
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const first = new Date(calYear, calMonth, 1);
            const startPad = (first.getDay() + 6) % 7;
            const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

            let html = '<div class="oc-planner-calendar__head">';
            html += '<button type="button" class="oc-planner-calendar__nav" data-cal-prev aria-label="Mese precedente">&lsaquo;</button>';
            html += '<span class="oc-planner-calendar__month">' + escapeHtml(MONTHS_IT[calMonth] + ' ' + calYear) + '</span>';
            html += '<button type="button" class="oc-planner-calendar__nav" data-cal-next aria-label="Mese successivo">&rsaquo;</button>';
            html += '</div><div class="oc-planner-calendar__weekdays">';
            WEEKDAYS_IT.forEach(function (w) {
                html += '<span>' + w + '</span>';
            });
            html += '</div><div class="oc-planner-calendar__days">';

            for (let i = 0; i < startPad; i++) {
                html += '<span class="oc-planner-calendar__day is-empty"></span>';
            }
            for (let d = 1; d <= daysInMonth; d++) {
                const cellDate = new Date(calYear, calMonth, d);
                const isPast = cellDate < today;
                const isStart = rangeStart && rangeStart.y === calYear && rangeStart.m === calMonth && rangeStart.d === d;
                const isEnd = rangeEnd && rangeEnd.y === calYear && rangeEnd.m === calMonth && rangeEnd.d === d;
                const inRange = dayInRange(calYear, calMonth, d);
                let cls = 'oc-planner-calendar__day';
                if (isPast) {
                    cls += ' is-disabled';
                }
                if (inRange) {
                    cls += ' is-in-range';
                }
                if (isStart) {
                    cls += ' is-start';
                }
                if (isEnd) {
                    cls += ' is-end';
                }
                html += '<button type="button" class="' + cls + '" data-day="' + d + '"' + (isPast ? ' disabled' : '') + '>' + d + '</button>';
            }
            html += '</div>';
            calendarEl.innerHTML = html;

            calendarEl.querySelector('[data-cal-prev]')?.addEventListener('click', function () {
                calMonth -= 1;
                if (calMonth < 0) {
                    calMonth = 11;
                    calYear -= 1;
                }
                renderCalendar();
            });
            calendarEl.querySelector('[data-cal-next]')?.addEventListener('click', function () {
                calMonth += 1;
                if (calMonth > 11) {
                    calMonth = 0;
                    calYear += 1;
                }
                renderCalendar();
            });
            calendarEl.querySelectorAll('[data-day]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const d = parseInt(btn.getAttribute('data-day'), 10);
                    const picked = { y: calYear, m: calMonth, d: d };
                    if (!rangeStart || (rangeStart && rangeEnd)) {
                        rangeStart = picked;
                        rangeEnd = null;
                    } else {
                        const s = new Date(rangeStart.y, rangeStart.m, rangeStart.d);
                        const e = new Date(picked.y, picked.m, picked.d);
                        if (e < s) {
                            rangeStart = picked;
                            rangeEnd = null;
                        } else {
                            const maxDays = (config && config.maxDays) ? config.maxDays : 7;
                            const diff = Math.round((e - s) / 86400000) + 1;
                            if (diff > maxDays) {
                                showError('Durata massima: ' + maxDays + ' giorni.');
                                return;
                            }
                            rangeEnd = picked;
                            showError('');
                        }
                    }
                    syncDateInputs();
                    renderCalendar();
                });
            });
        }

        function renderFocusCards(items) {
            if (!travelersEl) {
                return;
            }
            travelersEl.innerHTML = '';
            items.forEach(function (item) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'oc-planner-focus-card';
                btn.setAttribute('role', 'listitem');
                btn.dataset.id = item.id;
                const bg = item.image
                    ? ' style="background-image:url(\'' + String(item.image).replace(/'/g, '%27') + '\')"'
                    : '';
                btn.innerHTML =
                    '<span class="oc-planner-focus-card__bg"' + bg + '></span>'
                    + '<span class="oc-planner-focus-card__label">' + escapeHtml(item.name) + '</span>';
                btn.addEventListener('click', function () {
                    traveler = item.id;
                    travelersEl.querySelectorAll('.oc-planner-focus-card').forEach(function (el) {
                        el.classList.toggle('is-selected', el === btn);
                    });
                });
                travelersEl.appendChild(btn);
            });
        }

        function renderInterestCards(items) {
            if (!categoriesEl) {
                return;
            }
            categoriesEl.innerHTML = '';
            items.forEach(function (item) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'oc-planner-interest-card';
                btn.setAttribute('role', 'listitem');
                btn.dataset.id = item.id;
                const thumb = item.image
                    ? '<img class="oc-planner-interest-card__thumb" src="' + escapeHtml(item.image) + '" alt="" loading="lazy">'
                    : '<span class="oc-planner-interest-card__thumb oc-planner-interest-card__thumb--placeholder" aria-hidden="true"></span>';
                btn.innerHTML = thumb + '<span class="oc-planner-interest-card__label">' + escapeHtml(item.name) + '</span>';
                btn.addEventListener('click', function () {
                    if (categories.has(item.id)) {
                        categories.delete(item.id);
                        btn.classList.remove('is-selected');
                    } else {
                        categories.add(item.id);
                        btn.classList.add('is-selected');
                    }
                });
                categoriesEl.appendChild(btn);
            });
        }

        function renderOriginCards() {
            if (!originGrid) {
                return;
            }
            originGrid.innerHTML = '';
            VISITOR_COUNTRIES.forEach(function (country) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'oc-planner-origin-card';
                btn.setAttribute('role', 'radio');
                btn.setAttribute('aria-checked', 'false');
                btn.dataset.id = country.id;
                const flagUrl = 'https://flagcdn.com/w80/' + encodeURIComponent(country.flag) + '.png';
                btn.innerHTML =
                    '<span class="oc-planner-origin-card__radio" aria-hidden="true"></span>'
                    + '<img class="oc-planner-origin-card__flag" src="' + escapeHtml(flagUrl) + '" alt="" loading="lazy" width="80" height="53">'
                    + '<span class="oc-planner-origin-card__name">' + escapeHtml(country.name) + '</span>';
                btn.addEventListener('click', function () {
                    visitorOrigin = country.id;
                    originGrid.querySelectorAll('.oc-planner-origin-card').forEach(function (el) {
                        const selected = el === btn;
                        el.classList.toggle('is-selected', selected);
                        el.setAttribute('aria-checked', selected ? 'true' : 'false');
                    });
                });
                originGrid.appendChild(btn);
            });
        }

        function validateWizardStep() {
            const startDate = startDateInput ? startDateInput.value : '';
            const endDate = endDateInput ? endDateInput.value : '';

            if (lat === null || lng === null) {
                if (city) {
                    applyStartFromCityItem(findCityItem(city));
                } else if (config && config.defaultStart) {
                    lat = config.defaultStart.lat;
                    lng = config.defaultStart.lng;
                    startLocationLabel = config.defaultStart.address || startLocationLabel || '';
                    if (startInput && startLocationLabel && !startInput.value.trim()) {
                        startInput.value = startLocationLabel;
                    }
                } else {
                    applyStartFromCityItem(findCityItem(city) || cityItems[0]);
                }
            }
            if (lat === null || lng === null) {
                showError(cfg.i18n.pickStart || cfg.i18n.errorGeneric);
                return false;
            }
            if (!startDate || !endDate) {
                showError(cfg.i18n.pickDates);
                return false;
            }
            if (categories.size < 2) {
                showError(cfg.i18n.pickCategories);
                return false;
            }
            return true;
        }

        function runGenerate() {
            if (!visitorOrigin) {
                showError(cfg.i18n.pickOrigin || cfg.i18n.errorGeneric);
                return;
            }

            const startDate = startDateInput ? startDateInput.value : '';
            const endDate = endDateInput ? endDateInput.value : '';
            let startTime = startTimeInput ? startTimeInput.value : '10:00';
            if (!startTime) {
                startTime = '10:00';
            }
            if (startInput && startInput.value.trim()) {
                startLocationLabel = startInput.value.trim();
            }

            const btn = generateBtn;
            const submitLabel = btn ? btn.textContent : '';
            if (btn) {
                btn.disabled = true;
                btn.textContent = cfg.i18n.generating;
            }

            fetch(cfg.restUrl + '/generate', {
                method: 'POST',
                credentials: 'same-origin',
                headers: restHeaders(),
                body: JSON.stringify({
                    startDate: startDate,
                    endDate: endDate,
                    startTime: startTime,
                    startLocation: startLocationLabel,
                    lat: lat,
                    lng: lng,
                    city: city,
                    travelerType: traveler,
                    categories: Array.from(categories),
                    visitorOrigin: visitorOrigin,
                }),
            })
                .then(function (res) {
                    return res.json().then(function (body) {
                        return { ok: res.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.body.token) {
                        const msg =
                            (result.body && result.body.message) ||
                            (result.body && result.body.code) ||
                            cfg.i18n.errorGeneric;
                        throw new Error(msg);
                    }
                    const url =
                        cfg.itineraryPage +
                        (cfg.itineraryPage.indexOf('?') >= 0 ? '&' : '?') +
                        'token=' +
                        encodeURIComponent(result.body.token);
                    window.location.href = url;
                })
                .catch(function (err) {
                    showError(err.message || cfg.i18n.errorGeneric);
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = submitLabel;
                    }
                });
        }

        function tryInitStartAutocomplete() {
            initStartAutocomplete();
        }

        let configLoaded = false;

        if (landingStartBtn) {
            landingStartBtn.disabled = true;
        }

        function scheduleAutocompleteInit() {
            if (typeof google !== 'undefined' && google.maps?.places) {
                tryInitStartAutocomplete();
                return;
            }
            if (cfg.mapsReady) {
                document.addEventListener('opencomune-planner-maps-ready', tryInitStartAutocomplete, { once: true });
            }
        }

        fetch(cfg.restUrl + '/config', { credentials: 'same-origin' })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (!data.enabled) {
                    showError(cfg.i18n.errorGeneric);
                    return;
                }
                config = data;
                if (Array.isArray(data.cities) && data.cities.length) {
                    cityItems = data.cities;
                } else if (!cityItems.length && data.defaultStart) {
                    cityItems = [
                        {
                            id: '',
                            name: 'Tutto il territorio',
                            image: null,
                            center: data.defaultStart,
                        },
                    ];
                }

                if (cityItems.length === 1 && !landingCitySelection) {
                    applyCityItem(cityItems[0]);
                } else if (city && !landingCitySelection) {
                    const preset = findCityItem(city);
                    if (preset) {
                        applyCityItem(preset);
                    }
                }

                configLoaded = true;
                if (landingStartBtn) {
                    landingStartBtn.disabled = false;
                }

                renderFocusCards(data.travelerTypes || []);
                if (data.travelerTypes && data.travelerTypes[0]) {
                    traveler = data.travelerTypes[0].id;
                    const t0 = travelersEl.querySelector('.oc-planner-focus-card');
                    if (t0) {
                        t0.classList.add('is-selected');
                    }
                }

                renderInterestCards(data.categories || []);
                renderOriginCards();
                renderCalendar();
                scheduleAutocompleteInit();

                const initialStep = wizardRoot.dataset.initialStep || 'landing';
                if (initialStep === 'origin') {
                    setStep('origin');
                } else if (initialStep === 'wizard') {
                    setStep('wizard');
                } else {
                    setStep('landing');
                }
                window.opencomuneSyncPlannerHeaderHeight();
            })
            .catch(function () {
                showError(cfg.i18n.errorGeneric);
            });

        if (landingStartBtn) {
            landingStartBtn.addEventListener('click', function () {
                if (!configLoaded) {
                    showError(cfg.i18n.errorGeneric);
                    return;
                }
                showError('');

                let item = landingCitySelection;
                if (!item && landingCityInput) {
                    const typed = landingCityInput.value.trim();
                    if (typed) {
                        item = resolveCityFromInput(typed);
                    }
                }
                if (item) {
                    applyCityItem(item);
                } else if (config && config.defaultStart) {
                    city = '';
                    landingCitySelection = null;
                    lat = config.defaultStart.lat;
                    lng = config.defaultStart.lng;
                    startLocationLabel = config.defaultStart.address || '';
                    if (startInput && startLocationLabel) {
                        startInput.value = startLocationLabel;
                    }
                }

                setStep('wizard');
            });
        }

        if (continueBtn) {
            continueBtn.addEventListener('click', function () {
                if (!validateWizardStep()) {
                    return;
                }
                setStep('origin');
            });
        }

        if (generateBtn) {
            generateBtn.addEventListener('click', function () {
                if (!validateWizardStep()) {
                    setStep('wizard');
                    return;
                }
                runGenerate();
            });
        }
    }

    function bootstrapWizard() {
        init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapWizard);
    } else {
        bootstrapWizard();
    }
})();
