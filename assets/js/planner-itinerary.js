(function () {
    'use strict';

    function isContactStep() {
        const step = new URLSearchParams(window.location.search).get('step');
        return step === 'invia' || step === 'contact';
    }

    function setItineraryContactLayout(active) {
        const main = document.querySelector('.oc-planner--itinerary-full');
        if (main) {
            main.classList.toggle('oc-planner--itinerary-contact', active);
        }
    }

    function pushItineraryStep(step) {
        const url = new URL(window.location.href);
        if (step === 'invia') {
            url.searchParams.set('step', 'invia');
        } else {
            url.searchParams.delete('step');
        }
        history.pushState({ ocItineraryStep: step || 'itinerary' }, '', url.toString());
    }

    function showPlannerToast(message, variant, cfg) {
        if (window.opencomunePlannerAlert) {
            if (cfg && cfg.i18n && cfg.i18n.dismissAlert) {
                window.opencomunePlannerAlert.setDismissLabel(cfg.i18n.dismissAlert);
            }
            if (message) {
                window.opencomunePlannerAlert.show(message, variant);
            } else {
                window.opencomunePlannerAlert.clear();
            }
        }
    }

    function showRootAlert(root, message, variant, cfg) {
        showPlannerToast(message, variant, cfg);
        if (root) {
            root.classList.remove('oc-planner-itinerary-root--loading');
            root.innerHTML = '';
        }
    }

    function setContactFormAlert(message, cfg) {
        showPlannerToast(message || '', 'error', cfg);
    }

    function init() {
        const cfg = window.opencomunePlannerItinerary;
        const root = document.getElementById('oc-planner-itinerary');
        if (window.opencomunePlannerAlert && cfg && cfg.i18n && cfg.i18n.dismissAlert) {
            window.opencomunePlannerAlert.setDismissLabel(cfg.i18n.dismissAlert);
        }
        if (!cfg || !root || !cfg.token) {
            if (root) {
                showRootAlert(root, 'Itinerario non trovato.', 'error', cfg);
            }
            return;
        }

        fetch(cfg.restUrl + '/itinerary/' + encodeURIComponent(cfg.token), {
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': cfg.nonce },
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (!data || !Array.isArray(data.days)) {
                    showRootAlert(root, 'Itinerario non trovato.', 'error', cfg);
                    return;
                }
                const view = createItineraryView(root, data, cfg);
                function syncView() {
                    if (isContactStep()) {
                        view.renderContactPage();
                    } else {
                        view.render();
                    }
                }
                syncView();
                if (typeof window.opencomuneSyncPlannerHeaderHeight === 'function') {
                    window.opencomuneSyncPlannerHeaderHeight();
                }
                window.addEventListener('popstate', syncView);
            })
            .catch(function () {
                showRootAlert(root, 'Impossibile caricare l\'itinerario.', 'error', cfg);
            });
    }

    function createItineraryView(root, data, cfg) {
        const i18n = cfg.i18n || {};
        let activeDayIndex = 0;
        let map = null;
        let mapMarkers = [];
        let mapPolylines = [];
        let mapRouteRequestId = 0;

        function clearLoadingLayout() {
            root.classList.remove('oc-planner-itinerary-root--loading');
        }

        function cloneData(src) {
            return JSON.parse(JSON.stringify(src));
        }

        const state = cloneData(data);
        state.days = Array.isArray(state.days) ? state.days : [];

        function itineraryHasStops() {
            return state.days.some(function (day) {
                return Array.isArray(day.stops) && day.stops.length > 0;
            });
        }

        function applyStateFromTrip(trip) {
            if (!trip || typeof trip !== 'object') {
                return;
            }
            state.days = Array.isArray(trip.days) ? trip.days : [];
            if (trip.startLat != null) {
                state.startLat = trip.startLat;
            }
            if (trip.startLng != null) {
                state.startLng = trip.startLng;
            }
            if (trip.startAddress != null) {
                state.startAddress = trip.startAddress;
            }
            if (trip.startDate != null) {
                state.startDate = trip.startDate;
            }
            if (trip.endDate != null) {
                state.endDate = trip.endDate;
            }
            if (trip.startTime != null) {
                state.startTime = trip.startTime;
            }
            if (trip.emailSentAt != null && trip.emailSentAt !== '') {
                state.emailSentAt = trip.emailSentAt;
            }
        }

        const DAY_END_HOUR = 20;
        const DEFAULT_ROUTE_START_HOUR = 10;

        function parseTimeHour(time) {
            if (!time) {
                return null;
            }
            const parts = String(time).trim().substring(0, 5).split(':');
            if (parts.length < 2) {
                return null;
            }
            const h = parseInt(parts[0], 10);
            if (Number.isNaN(h) || h < 0 || h > 23) {
                return null;
            }
            return h;
        }

        function routeStartHour(days) {
            let minHour = parseTimeHour(state.startTime) ?? DEFAULT_ROUTE_START_HOUR;
            (days || []).forEach(function (day) {
                (day.stops || []).forEach(function (stop) {
                    const h = parseTimeHour(stop.arrive);
                    if (h !== null) {
                        minHour = Math.min(minHour, h);
                    }
                });
            });
            return Math.max(0, Math.min(23, minHour));
        }

        function ceilToHourMinutes(minutes) {
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return m > 0 ? (h + 1) * 60 : h * 60;
        }

        function getLeadingSlotCount(stops, startHour) {
            if (!stops.length) {
                return 0;
            }
            const anchor = startHour * 60;
            const first = timeToMinutes(stops[0].arrive);
            const diff = first - anchor;
            if (diff <= 0) {
                return 0;
            }
            return Math.floor(diff / 60);
        }

        function getGapSlotCount(stops, insertIndex) {
            if (insertIndex <= 0 || insertIndex >= stops.length) {
                return 0;
            }
            const prevEnd = timeToMinutes(stops[insertIndex - 1].leave || stops[insertIndex - 1].arrive);
            const nextStart = timeToMinutes(stops[insertIndex].arrive);
            const diff = nextStart - ceilToHourMinutes(prevEnd);
            if (diff <= 0) {
                return 0;
            }
            return Math.floor(diff / 60);
        }

        function getTrailingSlotCount(stops) {
            if (!stops.length) {
                return 0;
            }
            const last = stops[stops.length - 1];
            const endMin = timeToMinutes(last.leave || last.arrive);
            const h = Math.floor(endMin / 60);
            const m = endMin % 60;
            const budget = 60 * (DAY_END_HOUR - h) - m;
            if (budget <= 0) {
                return 0;
            }
            return Math.floor(budget / 60);
        }

        function getEmptyDaySlotCount(startHour) {
            return Math.max(0, DAY_END_HOUR - startHour);
        }

        function slotMaxDurationMinutes(dayIndex, insertIndex, subIndex, startHour) {
            const day = state.days[dayIndex];
            const stops = day && day.stops ? day.stops : [];
            let budget = 0;
            if (stops.length) {
                if (insertIndex <= 0) {
                    budget = Math.max(0, timeToMinutes(stops[0].arrive) - startHour * 60);
                } else if (insertIndex < stops.length) {
                    const prevEnd = timeToMinutes(
                        stops[insertIndex - 1].leave || stops[insertIndex - 1].arrive
                    );
                    budget = Math.max(
                        0,
                        timeToMinutes(stops[insertIndex].arrive) - ceilToHourMinutes(prevEnd)
                    );
                } else {
                    const lastEnd = timeToMinutes(
                        stops[stops.length - 1].leave || stops[stops.length - 1].arrive
                    );
                    const lastCeilHour = Math.floor(ceilToHourMinutes(lastEnd) / 60);
                    budget = Math.max(0, 60 * (DAY_END_HOUR - lastCeilHour));
                }
            } else {
                budget = Math.max(0, 60 * (DAY_END_HOUR - startHour));
            }
            return Math.max(0, budget - 60 * subIndex);
        }

        function slotStartMinutes(dayIndex, insertIndex, subIndex, startHour) {
            const day = state.days[dayIndex];
            const stops = day && day.stops ? day.stops : [];
            let base;
            if (insertIndex > 0 && stops[insertIndex - 1]) {
                base = ceilToHourMinutes(
                    timeToMinutes(stops[insertIndex - 1].leave || stops[insertIndex - 1].arrive)
                );
            } else {
                base = startHour * 60;
            }
            return base + subIndex * 60;
        }

        function formatMinutesAsTime(totalMinutes) {
            const h = Math.floor(totalMinutes / 60) % 24;
            const m = totalMinutes % 60;
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }

        function buildDayTimelineItems(day, startHour) {
            const stops = day.stops || [];
            const items = [];
            if (!stops.length) {
                const emptySlots = getEmptyDaySlotCount(startHour);
                for (let s = 0; s < emptySlots; s++) {
                    items.push({ type: 'add', insertIndex: 0, subIndex: s });
                }
                return items;
            }
            const leading = getLeadingSlotCount(stops, startHour);
            for (let s = 0; s < leading; s++) {
                items.push({ type: 'add', insertIndex: 0, subIndex: s });
            }
            stops.forEach(function (stop, stopIndex) {
                items.push({ type: 'card', stop: stop, stopIndex: stopIndex });
                const gap = getGapSlotCount(stops, stopIndex + 1);
                for (let s = 0; s < gap; s++) {
                    items.push({ type: 'add', insertIndex: stopIndex + 1, subIndex: s });
                }
            });
            const trailing = getTrailingSlotCount(stops);
            for (let s = 0; s < trailing; s++) {
                items.push({ type: 'add', insertIndex: stops.length, subIndex: s });
            }
            return items;
        }

        function restJsonHeaders() {
            return {
                'Content-Type': 'application/json',
                'X-WP-Nonce': cfg.nonce,
            };
        }

        function parseRestJsonResponse(res) {
            return res.json().then(function (body) {
                return { ok: res.ok, body: body };
            });
        }

        function restErrorMessage(result, fallback) {
            if (result && result.body && result.body.message) {
                return result.body.message;
            }
            return fallback || i18n.errorGeneric || 'Errore';
        }

        function removeDayIfEmpty(dayIndex) {
            const day = state.days[dayIndex];
            if (!day || !day.stops || day.stops.length > 0) {
                return false;
            }
            state.days.splice(dayIndex, 1);
            if (activeDayIndex > dayIndex) {
                activeDayIndex -= 1;
            } else if (activeDayIndex >= state.days.length) {
                activeDayIndex = Math.max(0, state.days.length - 1);
            }
            return true;
        }

        function renderRestartLink() {
            const url = cfg.plannerUrl || '/pianifica-itinerario/';
            const label = i18n.restart || 'Riavvia';
            const aria = i18n.restartAria || label;
            return (
                '<a href="' +
                escapeAttr(url) +
                '" class="oc-itinerary-restart" aria-label="' +
                escapeAttr(aria) +
                '">' +
                escapeHtml(label) +
                '</a>'
            );
        }

        function renderEmptyItinerary() {
            clearLoadingLayout();
            root.innerHTML =
                '<div class="oc-itinerary-view oc-itinerary-view--empty">' +
                '<div class="oc-itinerary-panel__head oc-itinerary-panel__head--empty">' +
                renderRestartLink() +
                '</div>' +
                '<p class="oc-itinerary-empty">' +
                escapeHtml(i18n.noStops || 'Nessuna tappa programmata.') +
                '</p>' +
                '</div>';
        }

        function formatDayLabel(iso) {
            if (!iso) {
                return '';
            }
            const parsed = new Date(iso + 'T12:00:00');
            if (Number.isNaN(parsed.getTime())) {
                return iso;
            }
            const label = parsed.toLocaleDateString('it-IT', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
            return label.charAt(0).toUpperCase() + label.slice(1);
        }

        function formatDayTabLabel(iso, index) {
            const dayLabel = (i18n.dayShort || 'Giorno %d').replace('%d', String(index + 1));
            if (!iso) {
                return { primary: dayLabel, secondary: '' };
            }
            const parsed = new Date(iso + 'T12:00:00');
            if (Number.isNaN(parsed.getTime())) {
                return { primary: dayLabel, secondary: iso };
            }
            const secondary = parsed.toLocaleDateString('it-IT', {
                weekday: 'short',
                day: 'numeric',
                month: 'short',
            });
            return { primary: dayLabel, secondary: secondary };
        }

        function timeToMinutes(time) {
            if (!time) {
                return 0;
            }
            const normalized = String(time).replace(/\s/g, '');
            const parts = normalized.split(':');
            if (parts.length < 2) {
                return 0;
            }
            const h = parseInt(parts[0], 10);
            const m = parseInt(parts[1], 10);
            if (Number.isNaN(h) || Number.isNaN(m)) {
                return 0;
            }
            return h * 60 + m;
        }

        function computeTimelineBounds(days) {
            const minHour = routeStartHour(days);
            const hourCount = Math.max(1, DAY_END_HOUR - minHour);
            return { minHour: minHour, hourCount: hourCount };
        }

        function formatDuration(minutes) {
            const mins = parseInt(minutes, 10);
            if (!mins || mins <= 0) {
                return '';
            }
            const h = Math.floor(mins / 60);
            const m = mins % 60;
            if (h && m) {
                return h + 'h ' + m + 'm';
            }
            if (h) {
                return h + 'h';
            }
            return m + 'm';
        }

        function stopDurationMinutes(stop) {
            if (stop.durationMin != null && stop.durationMin !== '') {
                const parsed = parseInt(stop.durationMin, 10);
                if (parsed > 0) {
                    return parsed;
                }
            }
            const card = stop.card || {};
            if (card.durationMin != null && card.durationMin !== '') {
                const parsed = parseInt(card.durationMin, 10);
                if (parsed > 0) {
                    return parsed;
                }
            }
            const arrive = stop.arrive || card.arrive || '';
            const leave = stop.leave || card.leave || '';
            if (arrive && leave) {
                const toMinutes = function (time) {
                    const parts = String(time).split(':');
                    if (parts.length < 2) {
                        return 0;
                    }
                    return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
                };
                return Math.max(0, toMinutes(leave) - toMinutes(arrive));
            }
            return 0;
        }

        function stopDurationLabel(stop) {
            return formatDuration(stopDurationMinutes(stop));
        }

        function stopCover(stop) {
            const card = stop.card || {};
            return card.coverImage || stop.coverImage || '';
        }

        function stopAvailableSlots(stop) {
            const card = stop.card || {};
            return Array.isArray(card.availableSlots) ? card.availableSlots : [];
        }

        function renderStopSlots(stop, i18n) {
            const slots = stopAvailableSlots(stop);
            if (!slots.length) {
                return '';
            }
            const label = i18n.slotsLabel || 'Orari disponibili';
            let html =
                '<div class="oc-itinerary-stop__slots">' +
                '<span class="oc-itinerary-stop__slots-label">' +
                escapeHtml(label) +
                '</span>' +
                '<div class="oc-itinerary-stop__slot-list" role="list">';
            slots.forEach(function (slot) {
                if (!slot || !slot.bookable) {
                    return;
                }
                const slotLabel = slot.label || slot.time || '';
                if (!slotLabel) {
                    return;
                }
                let title = slotLabel;
                if (slot.availableSpots != null && slot.availableSpots > 0) {
                    title += ' (' + slot.availableSpots + ' posti)';
                }
                html +=
                    '<span class="oc-itinerary-stop__slot" role="listitem" title="' +
                    escapeAttr(title) +
                    '">' +
                    escapeHtml(slotLabel) +
                    '</span>';
            });
            html += '</div></div>';
            return html;
        }

        function stopTitle(stop) {
            const card = stop.card || {};
            return card.title || stop.name || '';
        }

        function stopAddress(stop) {
            const card = stop.card || {};
            const line = (card.address || stop.address || '').trim();
            return line;
        }

        function clearMapOverlays() {
            mapMarkers.forEach(function (m) {
                m.setMap(null);
            });
            mapPolylines.forEach(function (p) {
                p.setMap(null);
            });
            mapMarkers = [];
            mapPolylines = [];
        }

        function decodeEncodedRoutePath(encoded) {
            if (!encoded) {
                return null;
            }
            if (
                google.maps.geometry &&
                google.maps.geometry.encoding &&
                google.maps.geometry.encoding.decodePath
            ) {
                return google.maps.geometry.encoding.decodePath(encoded);
            }
            return decodePolyline(encoded).map(function (p) {
                return new google.maps.LatLng(p.lat, p.lng);
            });
        }

        function requestDrivingRoutePath(start, stops, callback) {
            if (!google.maps || !google.maps.DirectionsService || !start.lat || !start.lng) {
                callback(null);
                return;
            }
            const validStops = (stops || []).filter(function (stop) {
                return stop && stop.lat && stop.lng;
            });
            if (!validStops.length) {
                callback(null);
                return;
            }

            const directionsService = new google.maps.DirectionsService();
            const destination = validStops[validStops.length - 1];
            const waypoints = validStops.slice(0, -1).map(function (stop) {
                return {
                    location: { lat: stop.lat, lng: stop.lng },
                    stopover: true,
                };
            });

            directionsService.route(
                {
                    origin: { lat: start.lat, lng: start.lng },
                    destination: { lat: destination.lat, lng: destination.lng },
                    waypoints: waypoints,
                    travelMode: google.maps.TravelMode.DRIVING,
                    optimizeWaypoints: false,
                },
                function (result, status) {
                    if (status !== google.maps.DirectionsStatus.OK || !result || !result.routes[0]) {
                        callback(null);
                        return;
                    }
                    callback(result.routes[0].overview_path || null);
                }
            );
        }

        function drawRouteOnMap(path, bounds, routeBrown) {
            if (!path || path.length < 2) {
                return;
            }
            const line = new google.maps.Polyline({
                path: path,
                geodesic: true,
                strokeColor: routeBrown,
                strokeOpacity: 0.95,
                strokeWeight: 5,
                map: map,
            });
            mapPolylines.push(line);
            path.forEach(function (ll) {
                bounds.extend(ll);
            });
        }

        function updateMapForDay(dayIndex) {
            if (typeof google === 'undefined' || !google.maps || !map) {
                return;
            }
            const requestId = ++mapRouteRequestId;
            clearMapOverlays();

            const day = state.days[dayIndex];
            if (!day) {
                return;
            }

            const bounds = new google.maps.LatLngBounds();
            const start = { lat: state.startLat, lng: state.startLng };
            const routeBrown = '#9a7342';
            const pinTeal = '#14b8a6';

            if (start.lat && start.lng) {
                bounds.extend(start);
                mapMarkers.push(
                    new google.maps.Marker({
                        position: start,
                        map: map,
                        title: i18n.startPoint || 'PUNTO DI PARTENZA',
                        zIndex: 1200,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 13,
                            fillColor: pinTeal,
                            fillOpacity: 1,
                            strokeColor: '#ffffff',
                            strokeWeight: 3,
                        },
                    })
                );
            }

            (day.stops || []).forEach(function (stop, index) {
                const pos = { lat: stop.lat, lng: stop.lng };
                if (!pos.lat && !pos.lng) {
                    return;
                }
                bounds.extend(pos);
                mapMarkers.push(
                    new google.maps.Marker({
                        position: pos,
                        map: map,
                        title: stopTitle(stop),
                        zIndex: 1100 + index,
                        label: {
                            text: String(index + 1),
                            color: '#ffffff',
                            fontSize: '11px',
                            fontWeight: '700',
                        },
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 15,
                            fillColor: routeBrown,
                            fillOpacity: 1,
                            strokeColor: '#ffffff',
                            strokeWeight: 2,
                        },
                    })
                );
            });

            const stops = day.stops || [];
            const encoded = day.encodedPolyline;
            let path = decodeEncodedRoutePath(encoded);

            if (path && path.length > 1) {
                drawRouteOnMap(path, bounds, routeBrown);
            } else if (start.lat && start.lng && stops.length > 0) {
                requestDrivingRoutePath(start, stops, function (drivingPath) {
                    if (requestId !== mapRouteRequestId) {
                        return;
                    }
                    if (drivingPath && drivingPath.length > 1) {
                        drawRouteOnMap(drivingPath, bounds, routeBrown);
                    }
                    if (!bounds.isEmpty()) {
                        map.fitBounds(bounds, 64);
                    }
                });
            }

            if (!bounds.isEmpty()) {
                map.fitBounds(bounds, 64);
            } else if (start.lat && start.lng) {
                map.setCenter(start);
                map.setZoom(12);
            }
        }

        function initMap() {
            if (typeof google === 'undefined' || !google.maps) {
                return;
            }
            const el = document.getElementById('oc-planner-itinerary-map');
            if (!el) {
                return;
            }
            map = new google.maps.Map(el, {
                center: { lat: state.startLat || 41.9028, lng: state.startLng || 12.4964 },
                zoom: 12,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
                clickableIcons: false,
            });
            updateMapForDay(activeDayIndex);
        }

        function renderDayTabsHtml(className, attrName) {
            let html =
                '<div class="' +
                className +
                '" role="tablist" aria-label="' +
                escapeAttr(i18n.dayTabs || 'Giorni') +
                '">';
            state.days.forEach(function (day, index) {
                const isActive = index === activeDayIndex;
                const tab = formatDayTabLabel(day.date, index);
                html +=
                    '<button type="button" class="oc-itinerary-tab' +
                    (isActive ? ' is-active' : '') +
                    '" role="tab" ' +
                    attrName +
                    '="' +
                    index +
                    '" aria-selected="' +
                    (isActive ? 'true' : 'false') +
                    '">';
                html += '<span class="oc-itinerary-tab__primary">' + escapeHtml(tab.primary) + '</span>';
                if (tab.secondary) {
                    html +=
                        '<span class="oc-itinerary-tab__secondary">' + escapeHtml(tab.secondary) + '</span>';
                }
                html += '</button>';
            });
            html += '</div>';
            return html;
        }

        function bindStopRemoveHandlers(scope) {
            scope.querySelectorAll('[data-stop-remove]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const item = btn.closest('[data-day-index]');
                    if (!item) {
                        return;
                    }
                    const dIdx = parseInt(item.getAttribute('data-day-index'), 10);
                    const sIdx = parseInt(item.getAttribute('data-stop-index'), 10);
                    if (!state.days[dIdx] || !state.days[dIdx].stops) {
                        return;
                    }
                    btn.disabled = true;
                    fetch(cfg.restUrl + '/itinerary/' + encodeURIComponent(cfg.token) + '/stops', {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: restJsonHeaders(),
                        body: JSON.stringify({ dayIndex: dIdx, stopIndex: sIdx }),
                    })
                        .then(parseRestJsonResponse)
                        .then(function (result) {
                            if (!result.ok) {
                                showPlannerToast(
                                    restErrorMessage(result, i18n.errorGeneric),
                                    'error',
                                    cfg
                                );
                                btn.disabled = false;
                                return;
                            }
                            applyStateFromTrip(result.body);
                            if (!itineraryHasStops()) {
                                renderEmptyItinerary();
                                return;
                            }
                            if (activeDayIndex >= state.days.length) {
                                activeDayIndex = Math.max(0, state.days.length - 1);
                            }
                            renderTimeline();
                            updateMapForDay(activeDayIndex);
                        })
                        .catch(function () {
                            showPlannerToast(i18n.errorGeneric || 'Errore', 'error', cfg);
                            btn.disabled = false;
                        });
                });
            });
        }

        function renderTimelineCard(stop, stopIndex, dayIndex) {
            const cover = stopCover(stop);
            const title = stopTitle(stop);
            const durLabel = stopDurationLabel(stop);
            const removeLabel = i18n.removeStop || 'Rimuovi tappa';
            const dragLabel = i18n.dragStop || 'Trascina per riordinare';
            const durMin = stopDurationMinutes(stop);
            const rowSpan = Math.max(1, Math.ceil((durMin || 60) / 60));
            let html =
                '<li class="oc-itinerary-timeline__stop-item" data-drop-day="' +
                dayIndex +
                '" data-drop-index="' +
                stopIndex +
                '">';
            html +=
                '<article class="oc-itinerary-timeline-card" draggable="true" data-day-index="' +
                dayIndex +
                '" data-stop-index="' +
                stopIndex +
                '" style="--card-hour-span:' +
                rowSpan +
                '">';
            html +=
                '<button type="button" class="oc-itinerary-timeline-card__drag" aria-label="' +
                escapeAttr(dragLabel) +
                '"><i class="bi bi-grip-vertical" aria-hidden="true"></i></button>';
            html += '<span class="oc-itinerary-timeline-card__badge" aria-hidden="true">' + (stopIndex + 1) + '</span>';
            html +=
                '<button type="button" class="oc-itinerary-timeline-card__remove" data-stop-remove aria-label="' +
                escapeAttr(removeLabel) +
                '"><i class="bi bi-trash" aria-hidden="true"></i></button>';
            if (cover) {
                html +=
                    '<img class="oc-itinerary-timeline-card__thumb" src="' +
                    escapeAttr(cover) +
                    '" alt="" loading="lazy">';
            } else {
                html +=
                    '<span class="oc-itinerary-timeline-card__thumb oc-itinerary-timeline-card__thumb--placeholder" aria-hidden="true"></span>';
            }
            html += '<p class="oc-itinerary-timeline-card__title">' + escapeHtml(title) + '</p>';
            if (durLabel) {
                html += '<p class="oc-itinerary-timeline-card__duration">' + escapeHtml(durLabel) + '</p>';
            }
            if (stop.arrive) {
                html +=
                    '<p class="oc-itinerary-timeline-card__time">' +
                    escapeHtml(stop.arrive) +
                    (stop.leave ? ' – ' + escapeHtml(stop.leave) : '') +
                    '</p>';
            }
            html += '</article></li>';
            return html;
        }

        function renderTimelineAddSlot(dayIndex, insertIndex, subIndex) {
            const aria =
                (i18n.addStopAria || 'Aggiungi tappa al percorso') +
                ' (' +
                formatMinutesAsTime(slotStartMinutes(dayIndex, insertIndex, subIndex, routeStartHour(state.days))) +
                ')';
            return (
                '<li class="oc-itinerary-timeline__add-item" data-drop-day="' +
                dayIndex +
                '" data-drop-index="' +
                insertIndex +
                '">' +
                '<button type="button" class="oc-itinerary-timeline__add" data-day-add="' +
                dayIndex +
                '" data-insert-index="' +
                insertIndex +
                '" data-sub-index="' +
                subIndex +
                '" aria-label="' +
                escapeAttr(aria) +
                '"><span aria-hidden="true">+</span></button></li>'
            );
        }

        let dragStopState = null;

        function postReorderStop(dayIndex, fromIndex, toIndex) {
            return fetch(cfg.restUrl + '/itinerary/' + encodeURIComponent(cfg.token) + '/stops/reorder', {
                method: 'PUT',
                credentials: 'same-origin',
                headers: restJsonHeaders(),
                body: JSON.stringify({
                    dayIndex: dayIndex,
                    fromIndex: fromIndex,
                    toIndex: toIndex,
                }),
            })
                .then(parseRestJsonResponse)
                .then(function (result) {
                    if (!result.ok) {
                        showPlannerToast(restErrorMessage(result, i18n.reorderStopError), 'error', cfg);
                        return false;
                    }
                    applyStateFromTrip(result.body);
                    renderTimeline();
                    setActiveDay(activeDayIndex);
                    updateMapForDay(activeDayIndex);
                    return true;
                })
                .catch(function () {
                    showPlannerToast(i18n.reorderStopError || i18n.errorGeneric, 'error', cfg);
                    return false;
                });
        }

        function bindTimelineDragHandlers(scope) {
            scope.querySelectorAll('.oc-itinerary-timeline-card').forEach(function (card) {
                card.addEventListener('dragstart', function (ev) {
                    if (ev.target.closest('.oc-itinerary-timeline-card__remove')) {
                        ev.preventDefault();
                        return;
                    }
                    const dayIndex = parseInt(card.getAttribute('data-day-index'), 10);
                    const stopIndex = parseInt(card.getAttribute('data-stop-index'), 10);
                    if (Number.isNaN(dayIndex) || Number.isNaN(stopIndex)) {
                        ev.preventDefault();
                        return;
                    }
                    dragStopState = { dayIndex: dayIndex, stopIndex: stopIndex };
                    card.classList.add('is-dragging');
                    if (ev.dataTransfer) {
                        ev.dataTransfer.effectAllowed = 'move';
                        ev.dataTransfer.setData('text/plain', String(stopIndex));
                    }
                });
                card.addEventListener('dragend', function () {
                    card.classList.remove('is-dragging');
                    scope.querySelectorAll('.oc-itinerary-timeline__stop-item.is-drop-target').forEach(function (el) {
                        el.classList.remove('is-drop-target');
                    });
                    dragStopState = null;
                });
            });

            scope.querySelectorAll('[data-drop-day]').forEach(function (target) {
                target.addEventListener('dragover', function (ev) {
                    if (!dragStopState) {
                        return;
                    }
                    const dayIndex = parseInt(target.getAttribute('data-drop-day'), 10);
                    if (dayIndex !== dragStopState.dayIndex) {
                        return;
                    }
                    ev.preventDefault();
                    if (ev.dataTransfer) {
                        ev.dataTransfer.dropEffect = 'move';
                    }
                    target.classList.add('is-drop-target');
                });
                target.addEventListener('dragleave', function () {
                    target.classList.remove('is-drop-target');
                });
                target.addEventListener('drop', function (ev) {
                    ev.preventDefault();
                    target.classList.remove('is-drop-target');
                    if (!dragStopState) {
                        return;
                    }
                    const dayIndex = parseInt(target.getAttribute('data-drop-day'), 10);
                    let toIndex = parseInt(target.getAttribute('data-drop-index'), 10);
                    const fromIndex = dragStopState.stopIndex;
                    if (dayIndex !== dragStopState.dayIndex || Number.isNaN(toIndex) || fromIndex === toIndex) {
                        return;
                    }
                    if (fromIndex < toIndex) {
                        toIndex -= 1;
                    }
                    if (fromIndex === toIndex) {
                        return;
                    }
                    postReorderStop(dayIndex, fromIndex, toIndex);
                });
            });
        }

        function renderTimeline() {
            const timelineEl = root.querySelector('[data-itinerary-timeline]');
            if (!timelineEl) {
                return;
            }

            const bounds = computeTimelineBounds(state.days);
            const minHour = bounds.minHour;
            const hourCount = bounds.hourCount;
            const startHour = routeStartHour(state.days);

            let hoursHtml = '';
            for (let i = 0; i < hourCount; i++) {
                hoursHtml +=
                    '<div class="oc-itinerary-timeline__hour"><span>' + (minHour + i) + '</span></div>';
            }

            let columnsHtml = '';
            state.days.forEach(function (day, dayIndex) {
                const isActive = dayIndex === activeDayIndex;
                columnsHtml +=
                    '<div class="oc-itinerary-timeline__day-col' +
                    (isActive ? ' is-active' : '') +
                    '" data-day-col="' +
                    dayIndex +
                    '">';
                columnsHtml += '<ul class="oc-itinerary-timeline__point-list">';
                buildDayTimelineItems(day, startHour).forEach(function (item) {
                    if (item.type === 'add') {
                        columnsHtml += renderTimelineAddSlot(dayIndex, item.insertIndex, item.subIndex);
                    } else {
                        columnsHtml += renderTimelineCard(item.stop, item.stopIndex, dayIndex);
                    }
                });
                columnsHtml += '</ul></div>';
            });

            timelineEl.innerHTML =
                '<div class="oc-itinerary-timeline__hours" style="--hour-count:' +
                hourCount +
                '">' +
                hoursHtml +
                '</div><div class="oc-itinerary-timeline__columns" style="--day-count:' +
                state.days.length +
                ';--hour-count:' +
                hourCount +
                '">' +
                columnsHtml +
                '</div>';

            bindStopRemoveHandlers(timelineEl);
            bindDayAddHandlers(timelineEl);
            bindTimelineDragHandlers(timelineEl);
        }

        function setActiveDay(dayIndex) {
            if (!state.days[dayIndex]) {
                return;
            }
            activeDayIndex = dayIndex;
            root.querySelectorAll('[data-map-tab]').forEach(function (tab) {
                const idx = parseInt(tab.getAttribute('data-map-tab'), 10);
                const isActive = idx === dayIndex;
                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            root.querySelectorAll('[data-day-col]').forEach(function (col) {
                const idx = parseInt(col.getAttribute('data-day-col'), 10);
                col.classList.toggle('is-active', idx === dayIndex);
            });
            updateMapForDay(dayIndex);
        }

        function bindDayTabHandlers() {
            if (root.dataset.dayTabsBound === '1') {
                return;
            }
            root.dataset.dayTabsBound = '1';
            root.addEventListener('click', function (ev) {
                const tab = ev.target.closest('[data-map-tab]');
                if (!tab || !root.contains(tab)) {
                    return;
                }
                const idx = parseInt(tab.getAttribute('data-map-tab'), 10);
                if (Number.isNaN(idx)) {
                    return;
                }
                setActiveDay(idx);
            });
        }

        let addStopDayIndex = 0;
        let addStopInsertIndex = 0;
        let addStopSubIndex = 0;
        let addStopMaxDuration = 0;
        let sidebarCollapsed = false;

        try {
            sidebarCollapsed = window.sessionStorage.getItem('ocItinerarySidebarCollapsed') === '1';
        } catch (e) {
            sidebarCollapsed = false;
        }

        function renderDatesModalShell() {
            return (
                '<div id="oc-itinerary-dates-modal" class="oc-itinerary-send-modal oc-itinerary-dates-modal" hidden>' +
                '<div class="oc-itinerary-send-modal__backdrop" data-dates-modal-close tabindex="-1" aria-hidden="true"></div>' +
                '<div class="oc-itinerary-send-modal__dialog oc-itinerary-add-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="oc-itinerary-dates-modal-title">' +
                '<button type="button" class="oc-itinerary-send-modal__close" data-dates-modal-close aria-label="' +
                escapeAttr(i18n.closeModal || i18n.dismissAlert || 'Chiudi') +
                '"><span aria-hidden="true">&times;</span></button>' +
                '<div class="oc-itinerary-add-modal__inner">' +
                '<h2 id="oc-itinerary-dates-modal-title" class="oc-itinerary-add-modal__title">' +
                escapeHtml(i18n.changeDatesTitle || 'Modifica date del viaggio') +
                '</h2>' +
                '<p class="oc-itinerary-add-modal__lead">' +
                escapeHtml(i18n.changeDatesLead || '') +
                '</p>' +
                '<form class="oc-itinerary-dates-modal__form" data-dates-form>' +
                '<div class="oc-itinerary-dates-modal__fields">' +
                '<div class="oc-itinerary-dates-modal__field">' +
                '<label for="oc-itinerary-date-start">' +
                escapeHtml(i18n.changeDatesStart || 'Data inizio') +
                '</label>' +
                '<input type="date" id="oc-itinerary-date-start" name="startDate" required>' +
                '</div>' +
                '<div class="oc-itinerary-dates-modal__field">' +
                '<label for="oc-itinerary-date-end">' +
                escapeHtml(i18n.changeDatesEnd || 'Data fine') +
                '</label>' +
                '<input type="date" id="oc-itinerary-date-end" name="endDate" required>' +
                '</div></div>' +
                '<button type="submit" class="oc-itinerary-dates-modal__submit" data-dates-submit>' +
                escapeHtml(i18n.changeDatesSave || 'Salva date') +
                '</button></form></div></div></div>'
            );
        }

        function closeDatesModal() {
            const modal = document.getElementById('oc-itinerary-dates-modal');
            if (!modal) {
                return;
            }
            modal.hidden = true;
            document.body.classList.remove('oc-itinerary-modal-open');
        }

        function openDatesModal() {
            const modal = document.getElementById('oc-itinerary-dates-modal');
            if (!modal) {
                return;
            }
            const startInput = modal.querySelector('#oc-itinerary-date-start');
            const endInput = modal.querySelector('#oc-itinerary-date-end');
            if (startInput) {
                startInput.value = state.startDate || '';
            }
            if (endInput) {
                endInput.value = state.endDate || '';
            }
            modal.hidden = false;
            document.body.classList.add('oc-itinerary-modal-open');
            if (startInput) {
                startInput.focus();
            }
        }

        function bindDatesModalChrome() {
            const modal = document.getElementById('oc-itinerary-dates-modal');
            if (!modal || modal.dataset.bound === '1') {
                return;
            }
            modal.dataset.bound = '1';
            modal.querySelectorAll('[data-dates-modal-close]').forEach(function (el) {
                el.addEventListener('click', closeDatesModal);
            });
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && modal && !modal.hidden) {
                    closeDatesModal();
                }
            });
            const form = modal.querySelector('[data-dates-form]');
            if (!form) {
                return;
            }
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                const startInput = form.querySelector('#oc-itinerary-date-start');
                const endInput = form.querySelector('#oc-itinerary-date-end');
                const startDate = startInput ? startInput.value : '';
                const endDate = endInput ? endInput.value : '';
                const submitBtn = form.querySelector('[data-dates-submit]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                fetch(cfg.restUrl + '/itinerary/' + encodeURIComponent(cfg.token) + '/dates', {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: restJsonHeaders(),
                    body: JSON.stringify({ startDate: startDate, endDate: endDate }),
                })
                    .then(parseRestJsonResponse)
                    .then(function (result) {
                        if (!result.ok) {
                            showPlannerToast(
                                restErrorMessage(result, i18n.changeDatesError),
                                'error',
                                cfg
                            );
                            return;
                        }
                        applyStateFromTrip(result.body);
                        closeDatesModal();
                        if (!itineraryHasStops()) {
                            renderEmptyItinerary();
                            return;
                        }
                        if (activeDayIndex >= state.days.length) {
                            activeDayIndex = Math.max(0, state.days.length - 1);
                        }
                        renderShell();
                        showPlannerToast(
                            i18n.changeDatesSuccess || 'Date aggiornate.',
                            'success',
                            cfg
                        );
                    })
                    .catch(function () {
                        showPlannerToast(i18n.changeDatesError || i18n.errorGeneric, 'error', cfg);
                    })
                    .finally(function () {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                        }
                    });
            });
        }

        function syncSidebarToggleUi() {
            const view = root.querySelector('[data-itinerary-view]');
            const btn = root.querySelector('[data-sidebar-toggle]');
            if (!view || !btn) {
                return;
            }
            view.classList.toggle('is-sidebar-collapsed', sidebarCollapsed);
            btn.setAttribute('aria-expanded', sidebarCollapsed ? 'false' : 'true');
            btn.setAttribute(
                'aria-label',
                sidebarCollapsed
                    ? i18n.toggleSidebarShow || 'Mostra timeline'
                    : i18n.toggleSidebarHide || 'Nascondi timeline'
            );
            const icon = btn.querySelector('[data-toggle-icon]');
            if (icon) {
                icon.className = sidebarCollapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
            }
            try {
                window.sessionStorage.setItem('ocItinerarySidebarCollapsed', sidebarCollapsed ? '1' : '0');
            } catch (e) {
                /* ignore */
            }
            if (map && typeof google !== 'undefined' && google.maps) {
                window.requestAnimationFrame(function () {
                    google.maps.event.trigger(map, 'resize');
                    updateMapForDay(activeDayIndex);
                });
            }
        }

        function bindSidebarToggle() {
            const btn = root.querySelector('[data-sidebar-toggle]');
            if (!btn || btn.dataset.bound === '1') {
                return;
            }
            btn.dataset.bound = '1';
            syncSidebarToggleUi();
            btn.addEventListener('click', function () {
                sidebarCollapsed = !sidebarCollapsed;
                syncSidebarToggleUi();
            });
        }

        function renderAddStopModalShell() {
            return (
                '<div id="oc-itinerary-add-modal" class="oc-itinerary-send-modal oc-itinerary-add-modal" hidden>' +
                '<div class="oc-itinerary-send-modal__backdrop" data-add-modal-close tabindex="-1" aria-hidden="true"></div>' +
                '<div class="oc-itinerary-send-modal__dialog oc-itinerary-add-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="oc-itinerary-add-modal-title">' +
                '<button type="button" class="oc-itinerary-send-modal__close" data-add-modal-close aria-label="' +
                escapeAttr(i18n.closeModal || i18n.dismissAlert || 'Chiudi') +
                '"><span aria-hidden="true">&times;</span></button>' +
                '<div class="oc-itinerary-add-modal__inner">' +
                '<h2 id="oc-itinerary-add-modal-title" class="oc-itinerary-add-modal__title">' +
                escapeHtml(i18n.addStopTitle || 'Aggiungi al percorso') +
                '</h2>' +
                '<p class="oc-itinerary-add-modal__lead">' +
                escapeHtml(i18n.addStopLead || '') +
                '</p>' +
                '<div class="oc-itinerary-add-modal__list" data-add-candidates-list></div>' +
                '</div></div></div>'
            );
        }

        function closeAddStopModal() {
            const modal = document.getElementById('oc-itinerary-add-modal');
            if (!modal) {
                return;
            }
            modal.hidden = true;
            document.body.classList.remove('oc-itinerary-modal-open');
        }

        function renderCandidateListItems(candidates) {
            if (!candidates || candidates.length === 0) {
                return (
                    '<div class="oc-itinerary-notice oc-itinerary-add-modal__notice" role="status">' +
                    '<p class="oc-itinerary-notice__text oc-itinerary-add-modal__notice-text">' +
                    escapeHtml(
                        i18n.addStopEmpty ||
                            'Al momento non ci sono altre proposte per questo orario.'
                    ) +
                    '</p></div>'
                );
            }
            let html = '<ul class="oc-itinerary-add-modal__items">';
            candidates.forEach(function (item) {
                const typeLabel =
                    item.source === 'poi'
                        ? i18n.addStopTypePoi || 'Destinazione'
                        : i18n.addStopTypeExperience || 'Esperienza';
                const dur = formatDuration(item.durationMin);
                html += '<li class="oc-itinerary-add-modal__item">';
                html +=
                    '<button type="button" class="oc-itinerary-add-modal__pick" data-pick-source="' +
                    escapeAttr(item.source) +
                    '" data-pick-id="' +
                    escapeAttr(String(item.id)) +
                    '">';
                if (item.coverImage) {
                    html +=
                        '<img class="oc-itinerary-add-modal__thumb" src="' +
                        escapeAttr(item.coverImage) +
                        '" alt="" loading="lazy">';
                } else {
                    html += '<span class="oc-itinerary-add-modal__thumb oc-itinerary-add-modal__thumb--placeholder"></span>';
                }
                html += '<span class="oc-itinerary-add-modal__meta">';
                html += '<span class="oc-itinerary-add-modal__type">' + escapeHtml(typeLabel) + '</span>';
                html += '<span class="oc-itinerary-add-modal__name">' + escapeHtml(item.title || '') + '</span>';
                if (dur) {
                    html += '<span class="oc-itinerary-add-modal__duration">' + escapeHtml(dur) + '</span>';
                }
                html += '</span></button></li>';
            });
            html += '</ul>';
            return html;
        }

        function bindAddStopPickHandlers(scope) {
            scope.querySelectorAll('[data-pick-source]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const source = btn.getAttribute('data-pick-source');
                    const id = parseInt(btn.getAttribute('data-pick-id'), 10);
                    if (!source || !id) {
                        return;
                    }
                    btn.disabled = true;
                    fetch(cfg.restUrl + '/itinerary/' + encodeURIComponent(cfg.token) + '/stops', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: restJsonHeaders(),
                        body: JSON.stringify({
                            dayIndex: addStopDayIndex,
                            insertIndex: addStopInsertIndex,
                            source: source,
                            id: id,
                        }),
                    })
                        .then(parseRestJsonResponse)
                        .then(function (result) {
                            if (!result.ok) {
                                showPlannerToast(
                                    restErrorMessage(result, i18n.addStopError),
                                    'error',
                                    cfg
                                );
                                btn.disabled = false;
                                return;
                            }
                            applyStateFromTrip(result.body);
                            closeAddStopModal();
                            if (!itineraryHasStops()) {
                                renderEmptyItinerary();
                                return;
                            }
                            if (addStopDayIndex >= 0 && addStopDayIndex < state.days.length) {
                                activeDayIndex = addStopDayIndex;
                            }
                            renderTimeline();
                            setActiveDay(activeDayIndex);
                            if (map) {
                                updateMapForDay(activeDayIndex);
                            } else {
                                initMap();
                            }
                            showPlannerToast(
                                i18n.addStopSuccess || 'Tappa aggiunta al percorso.',
                                'success',
                                cfg
                            );
                        })
                        .catch(function () {
                            showPlannerToast(i18n.addStopError || i18n.errorGeneric, 'error', cfg);
                            btn.disabled = false;
                        });
                });
            });
        }

        function openAddStopModal(dayIndex, insertIndex, subIndex) {
            addStopDayIndex = dayIndex;
            addStopInsertIndex = insertIndex;
            addStopSubIndex = subIndex;
            const startHour = routeStartHour(state.days);
            addStopMaxDuration = slotMaxDurationMinutes(dayIndex, insertIndex, subIndex, startHour);
            const slotTime = formatMinutesAsTime(slotStartMinutes(dayIndex, insertIndex, subIndex, startHour));
            const modal = document.getElementById('oc-itinerary-add-modal');
            const listEl = modal ? modal.querySelector('[data-add-candidates-list]') : null;
            const titleEl = modal ? modal.querySelector('#oc-itinerary-add-modal-title') : null;
            if (!modal || !listEl) {
                return;
            }
            if (titleEl) {
                const dayLabel = (i18n.dayShort || 'Giorno %d').replace('%d', String(dayIndex + 1));
                titleEl.textContent =
                    (i18n.addStopTitle || 'Aggiungi al percorso') + ' – ' + dayLabel + ' ' + slotTime;
            }
            modal.hidden = false;
            document.body.classList.add('oc-itinerary-modal-open');
            listEl.innerHTML =
                '<p class="oc-itinerary-add-modal__loading">' +
                escapeHtml(i18n.addStopLoading || 'Caricamento...') +
                '</p>';

            let url =
                cfg.restUrl + '/itinerary/' + encodeURIComponent(cfg.token) + '/candidates';
            if (addStopMaxDuration > 0) {
                url += '?maxDurationMin=' + encodeURIComponent(String(addStopMaxDuration));
            }

            fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': cfg.nonce },
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (body) {
                    const candidates = body && Array.isArray(body.candidates) ? body.candidates : [];
                    listEl.innerHTML = renderCandidateListItems(candidates);
                    bindAddStopPickHandlers(listEl);
                })
                .catch(function () {
                    listEl.innerHTML =
                        '<p class="oc-itinerary-add-modal__empty">' +
                        escapeHtml(i18n.errorGeneric || 'Errore') +
                        '</p>';
                });
        }

        function bindAddStopModalChrome() {
            const modal = document.getElementById('oc-itinerary-add-modal');
            if (!modal || modal.dataset.bound === '1') {
                return;
            }
            modal.dataset.bound = '1';
            modal.querySelectorAll('[data-add-modal-close]').forEach(function (el) {
                el.addEventListener('click', closeAddStopModal);
            });
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && modal && !modal.hidden) {
                    closeAddStopModal();
                }
            });
        }

        function bindDayAddHandlers(scope) {
            scope.querySelectorAll('[data-day-add]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const dayIndex = parseInt(btn.getAttribute('data-day-add'), 10);
                    const insertIndex = parseInt(btn.getAttribute('data-insert-index'), 10);
                    const subIndex = parseInt(btn.getAttribute('data-sub-index'), 10);
                    if (Number.isNaN(dayIndex)) {
                        return;
                    }
                    openAddStopModal(
                        dayIndex,
                        Number.isNaN(insertIndex) ? 0 : insertIndex,
                        Number.isNaN(subIndex) ? 0 : subIndex
                    );
                });
            });
        }

        function renderShell() {
            if (!itineraryHasStops()) {
                renderEmptyItinerary();
                return;
            }

            let initialDayIndex = state.days.findIndex(function (day) {
                return day.stops && day.stops.length > 0;
            });
            if (initialDayIndex >= 0) {
                activeDayIndex = initialDayIndex;
            }

            clearLoadingLayout();

            let html = '<div class="oc-itinerary-view oc-itinerary-view--timeline" data-itinerary-view>';
            html += '<aside class="oc-itinerary-sidebar">';
            html += '<div class="oc-itinerary-timeline" data-itinerary-timeline></div>';
            html += '</aside>';

            html += '<div class="oc-itinerary-map-panel">';
            html +=
                '<button type="button" class="oc-itinerary-sidebar__toggle" data-sidebar-toggle aria-expanded="true">' +
                '<i class="bi bi-chevron-left" data-toggle-icon aria-hidden="true"></i></button>';
            html += '<div class="oc-itinerary-map-toolbar">';
            html += renderDayTabsHtml('oc-itinerary-map-toolbar__tabs', 'data-map-tab');
            html += '<div class="oc-itinerary-map-toolbar__actions">';
            html +=
                '<button type="button" class="oc-itinerary-map-action" data-send-route>' +
                escapeHtml(i18n.sendRoute || 'Invia percorso') +
                '</button>';
            html +=
                '<button type="button" class="oc-itinerary-map-action oc-itinerary-map-action--link" data-change-dates>' +
                escapeHtml(i18n.changeStartEnd || 'Modifica date') +
                '</button>';
            html += renderRestartLink();
            html += '</div></div>';
            html +=
                '<div id="oc-planner-itinerary-map" class="oc-itinerary-map" aria-label="' +
                escapeAttr(i18n.mapAria || 'Mappa itinerario') +
                '"></div>';
            html += '</div></div>';
            html += renderAddStopModalShell();
            html += renderDatesModalShell();

            root.innerHTML = html;

            bindDayTabHandlers();
            bindAddStopModalChrome();
            bindDatesModalChrome();
            bindSidebarToggle();
            bindSendRouteNavigation(root);
            const datesBtn = root.querySelector('[data-change-dates]');
            if (datesBtn) {
                datesBtn.addEventListener('click', openDatesModal);
            }

            renderTimeline();
            initMap();
        }

        function renderContactPage() {
            setItineraryContactLayout(true);
            clearLoadingLayout();
            root.innerHTML = renderContactPageHtml(state, cfg);
            bindContactPageForm(root, cfg, state, function () {
                pushItineraryStep('');
                renderShell();
            });
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function bindSendRouteNavigation(scope) {
            const trigger = scope.querySelector('[data-send-route]');
            if (!trigger) {
                return;
            }
            trigger.addEventListener('click', function () {
                pushItineraryStep('invia');
                renderContactPage();
            });
        }

        return {
            render: function () {
                setItineraryContactLayout(false);
                renderShell();
            },
            renderContactPage: renderContactPage,
        };
    }

    function renderContactPageHtml(data, cfg) {
        const i18n = cfg.i18n || {};
        const termsUrl = cfg.termsUrl || '/termini-e-condizioni/';

        let html = '<div class="oc-itinerary-contact-page">';
        html += '<div class="oc-itinerary-contact-page__inner">';
        html +=
            '<h1 class="oc-itinerary-contact-page__title">' +
            escapeHtml(i18n.contactTitle || 'Informazioni turistiche') +
            '</h1>';

        if (data.contactSubmitState === 'success' || data.emailSentAt) {
            const isSuccess = data.contactSubmitState === 'success';
            const noticeClass = isSuccess
                ? 'oc-itinerary-notice oc-itinerary-notice--success'
                : 'oc-itinerary-notice';
            const noticeText = isSuccess
                ? i18n.success || 'Richiesta inviata. Ti contatteremo al più presto.'
                : i18n.alreadySent || 'Hai già inviato una richiesta per questo itinerario.';
            html +=
                '<div class="' +
                noticeClass +
                '" role="status">' +
                '<p class="oc-itinerary-notice__text">' +
                escapeHtml(noticeText) +
                '</p></div>';
            html +=
                '<p class="oc-itinerary-contact-form__actions"><button type="button" class="oc-itinerary-contact-form__back" data-contact-back>' +
                escapeHtml(i18n.back || 'Indietro') +
                '</button></p>';
            html += '</div></div>';
            return html;
        }

        html += '<form class="oc-itinerary-contact-form" id="oc-planner-contact-form" novalidate>';

        html += '<div class="oc-itinerary-contact-form__field">';
        html +=
            '<label for="oc-planner-contact-email">' +
            escapeHtml(i18n.email || 'Email') +
            '*</label>';
        html +=
            '<input type="email" id="oc-planner-contact-email" name="email" required autocomplete="email" value="' +
            escapeAttr(data.contactEmail || '') +
            '">';
        html +=
            '<p class="oc-itinerary-contact-form__hint">' +
            escapeHtml(i18n.required || 'Obbligatorio') +
            '</p>';
        html += '</div>';

        html += '<div class="oc-itinerary-contact-form__field">';
        html +=
            '<label for="oc-planner-contact-name">' +
            escapeHtml(i18n.name || 'Nome') +
            '*</label>';
        html +=
            '<input type="text" id="oc-planner-contact-name" name="name" required minlength="3" autocomplete="name" value="' +
            escapeAttr(data.contactName || '') +
            '">';
        html +=
            '<p class="oc-itinerary-contact-form__hint">' +
            escapeHtml(i18n.nameHint || 'Obbligatorio e almeno 3 caratteri') +
            '</p>';
        html += '</div>';

        html += '<div class="oc-itinerary-contact-form__field">';
        html +=
            '<label for="oc-planner-contact-notes">' +
            escapeHtml(i18n.notes || 'Informazioni aggiuntive') +
            '</label>';
        html +=
            '<textarea id="oc-planner-contact-notes" name="notes" rows="5">' +
            escapeHtml(data.contactNotes || '') +
            '</textarea>';
        html += '</div>';

        html += '<div class="oc-itinerary-contact-form__terms">';
        html += '<input type="checkbox" id="oc-planner-contact-terms" name="terms" value="1">';
        html +=
            '<label for="oc-planner-contact-terms">' +
            escapeHtml(i18n.termsLabel || 'Ho letto e accetto i') +
            ' <a href="' +
            escapeAttr(termsUrl) +
            '" target="_blank" rel="noopener noreferrer">' +
            escapeHtml(i18n.termsLink || 'termini e condizioni d\'uso') +
            '</a>*</label>';
        html += '</div>';

        if (cfg.turnstileRequired) {
            html +=
                '<div id="oc-planner-contact-turnstile-host" class="oc-itinerary-contact-form__turnstile" aria-live="polite"></div>';
        }

        html += '<div class="oc-itinerary-contact-form__actions">';
        html +=
            '<button type="button" class="oc-itinerary-contact-form__back" data-contact-back>' +
            escapeHtml(i18n.back || 'Indietro') +
            '</button>';
        html +=
            '<button type="submit" class="oc-itinerary-contact-form__submit" id="oc-planner-contact-submit" disabled>' +
            escapeHtml(i18n.sendRoute || 'Invia percorso') +
            '</button>';
        html += '</div></form></div></div>';

        return html;
    }

    function contactPageIsComplete(data) {
        return !!(data.emailSentAt || data.contactSubmitState === 'success');
    }

    let contactTurnstileMounted = false;

    function ensureContactTurnstile(root, cfg) {
        if (!cfg.turnstileRequired || contactTurnstileMounted) {
            return;
        }
        const host = root.querySelector('#oc-planner-contact-turnstile-host');
        if (!host || typeof turnstile === 'undefined' || !cfg.turnstileSiteKey) {
            return;
        }
        turnstile.render(host, { sitekey: cfg.turnstileSiteKey });
        contactTurnstileMounted = true;
    }

    function resetContactTurnstile(root, cfg) {
        if (!cfg.turnstileRequired || typeof turnstile === 'undefined') {
            return;
        }
        const host = root.querySelector('#oc-planner-contact-turnstile-host');
        if (!host) {
            contactTurnstileMounted = false;
            return;
        }
        try {
            turnstile.reset(host);
        } catch (e) {
            host.innerHTML = '';
            contactTurnstileMounted = false;
            ensureContactTurnstile(root, cfg);
        }
    }

    function getContactTurnstileToken(root) {
        const field = root.querySelector('[name="cf-turnstile-response"]');
        return field && field.value ? String(field.value).trim() : '';
    }

    function bindContactPageForm(root, cfg, data, onBack) {
        const i18n = cfg.i18n || {};
        const form = root.querySelector('#oc-planner-contact-form');
        const backButtons = root.querySelectorAll('[data-contact-back]');
        backButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (typeof onBack === 'function') {
                    onBack();
                }
            });
        });

        if (!form || contactPageIsComplete(data)) {
            return;
        }

        contactTurnstileMounted = false;
        ensureContactTurnstile(root, cfg);

        const submitBtn = root.querySelector('#oc-planner-contact-submit');
        const termsInput = root.querySelector('#oc-planner-contact-terms');

        function formIsValid() {
            const name = form.name.value.trim();
            const email = form.email.value.trim();
            const terms = termsInput && termsInput.checked;
            if (!terms) {
                return false;
            }
            if (name.length < 3) {
                return false;
            }
            if (!email || !form.email.checkValidity()) {
                return false;
            }
            if (cfg.turnstileRequired && !getContactTurnstileToken(root)) {
                return false;
            }
            return true;
        }

        function syncSubmitState() {
            if (submitBtn) {
                submitBtn.disabled = !formIsValid();
            }
        }

        form.addEventListener('input', syncSubmitState);
        form.addEventListener('change', syncSubmitState);
        syncSubmitState();

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (!formIsValid()) {
                if (cfg.turnstileRequired && !getContactTurnstileToken(root)) {
                    setContactFormAlert(
                        i18n.turnstileMessage ||
                            'Completa la verifica anti-spam prima di inviare.',
                        cfg
                    );
                    ensureContactTurnstile(root, cfg);
                } else {
                    setContactFormAlert(
                        i18n.termsRequired || i18n.errorGeneric || 'Compila tutti i campi obbligatori.',
                        cfg
                    );
                }
                return;
            }
            setContactFormAlert('', cfg);

            const payload = {
                name: form.name.value.trim(),
                email: form.email.value.trim(),
                phone: '',
                notes: form.notes.value.trim(),
                acceptTerms: true,
            };
            const turnstileToken = getContactTurnstileToken(root);
            if (turnstileToken) {
                payload.turnstileToken = turnstileToken;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
            }

            fetch(cfg.restUrl + '/itinerary/' + encodeURIComponent(cfg.token) + '/contact', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': cfg.nonce,
                },
                body: JSON.stringify(payload),
            })
                .then(function (res) {
                    return res.json().then(function (body) {
                        return { ok: res.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        const code = result.body && result.body.code;
                        if (code === 'already_sent') {
                            data.emailSentAt =
                                (result.body.data && result.body.data.emailSentAt) || true;
                            delete data.contactSubmitState;
                            root.innerHTML = renderContactPageHtml(data, cfg);
                            bindContactPageForm(root, cfg, data, onBack);
                            return;
                        }
                        let msg =
                            (result.body && result.body.message) ||
                            i18n.errorGeneric ||
                            'Invio non riuscito.';
                        setContactFormAlert(msg, cfg);
                        resetContactTurnstile(root, cfg);
                        syncSubmitState();
                        return;
                    }
                    data.emailSentAt = result.body.emailSentAt || true;
                    data.contactSubmitState = 'success';
                    data.contactName = result.body.contactName || payload.name;
                    data.contactEmail = payload.email;
                    root.innerHTML = renderContactPageHtml(data, cfg);
                    bindContactPageForm(root, cfg, data, onBack);
                })
                .catch(function () {
                    setContactFormAlert(i18n.errorGeneric || 'Invio non riuscito.', cfg);
                    resetContactTurnstile(root, cfg);
                    syncSubmitState();
                });
        });
    }

    function decodePolyline(encoded) {
        const points = [];
        let index = 0;
        let lat = 0;
        let lng = 0;

        while (index < encoded.length) {
            let shift = 0;
            let result = 0;
            let byte;
            do {
                byte = encoded.charCodeAt(index++) - 63;
                result |= (byte & 0x1f) << shift;
                shift += 5;
            } while (byte >= 0x20);
            lat += result & 1 ? ~(result >> 1) : result >> 1;

            shift = 0;
            result = 0;
            do {
                byte = encoded.charCodeAt(index++) - 63;
                result |= (byte & 0x1f) << shift;
                shift += 5;
            } while (byte >= 0x20);
            lng += result & 1 ? ~(result >> 1) : result >> 1;

            points.push({ lat: lat / 1e5, lng: lng / 1e5 });
        }

        return points;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return escapeHtml(str);
    }

    function syncPlannerAppHeaderHeight() {
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
    }

    function bindPlannerAppHeaderHeightSync() {
        if (!document.body.classList.contains('oc-planner-wizard-flow')) {
            return;
        }
        if (typeof window.opencomuneSyncPlannerHeaderHeight !== 'function') {
            window.opencomuneSyncPlannerHeaderHeight = syncPlannerAppHeaderHeight;
        }
        window.opencomuneSyncPlannerHeaderHeight();
        window.addEventListener('resize', function () {
            if (typeof window.opencomuneSyncPlannerHeaderHeight === 'function') {
                window.opencomuneSyncPlannerHeaderHeight();
            }
        });
    }

    function bootstrapItinerary() {
        bindPlannerAppHeaderHeightSync();
        const hasGoogleScript = document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]');
        if (!hasGoogleScript || window.opencomunePlannerItineraryMapsLoaded) {
            init();
            return;
        }
        document.addEventListener('opencomune-planner-itinerary-maps-ready', init, { once: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapItinerary);
    } else {
        bootstrapItinerary();
    }
})();
