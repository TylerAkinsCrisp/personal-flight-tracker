/**
 * Track Tyler Flight Tracker
 * Public site JavaScript for flight display and countdowns
 */

class FlightTracker {
    constructor() {
        this.currentTrip = null;
        this.pastTrips = [];
        this.countdownIntervals = [];
        this.init();
    }

    async init() {
        await this.loadTrips();
        this.startCountdowns();
    }

    async loadTrips() {
        try {
            const response = await fetch('/api/trips.php?include_past=true');
            const data = await response.json();

            if (data.success) {
                this.currentTrip = data.current_trip;
                this.pastTrips = data.past_trips || [];
                this.render();
            } else {
                console.error('Failed to load trips:', data.error);
                this.showError('Failed to load trip data');
            }
        } catch (error) {
            console.error('Error loading trips:', error);
            this.showError('Error loading trip data');
        }
    }

    render() {
        if (!this.currentTrip) {
            this.renderNoTrip();
            return;
        }

        this.renderCurrentTrip();
        this.renderPastTrips();
    }

    renderCurrentTrip() {
        const trip = this.currentTrip;

        // Update page title
        document.title = `Track Tyler - ${trip.name}`;

        // Update hero section if it exists
        const heroTitle = document.querySelector('.hero-title');
        if (heroTitle) {
            heroTitle.innerHTML = `<i class="bi bi-heart-fill text-danger"></i> ${this.escapeHtml(trip.name)}`;
        }

        // Render directions
        ['departure', 'return'].forEach(dirType => {
            this.renderDirection(dirType, trip.directions[dirType]);
        });
    }

    renderDirection(dirType, direction) {
        const container = document.getElementById(`${dirType}-segments`);
        if (!container || !direction || !direction.segments) return;

        container.innerHTML = '';

        if (direction.segments.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">No flights scheduled</p>';
            return;
        }

        direction.segments.forEach((segment, index) => {
            const card = this.createFlightCard(segment, dirType);
            container.appendChild(card);

            // Add connector between segments
            if (index < direction.segments.length - 1) {
                const connector = document.createElement('div');
                connector.className = 'segment-connector';
                container.appendChild(connector);
            }
        });
    }

    createFlightCard(segment, dirType) {
        const div = document.createElement('div');
        div.className = 'flight-card';

        const duration = this.formatDuration(segment.duration_minutes);
        const depTime = this.formatTime(segment.scheduled_departure);
        const arrTime = this.formatTime(segment.scheduled_arrival);
        const depDate = this.formatDate(segment.scheduled_departure);
        const arrDate = this.formatDate(segment.scheduled_arrival);

        let trackingHtml = '';
        if (segment.radarbox_id) {
            trackingHtml = `
                <div class="radarbox-embed mt-3">
                    <iframe
                        src="https://www.airnavradar.com/?widget=1&z=7&fid=${this.escapeHtml(segment.radarbox_id)}"
                        loading="lazy"
                        title="Live Flight Tracking for ${this.escapeHtml(segment.flight_number)}">
                    </iframe>
                </div>`;
        } else {
            const trackUrl = segment.flightaware_url ||
                `https://flightaware.com/live/flight/${segment.flight_number.replace(' ', '')}`;
            trackingHtml = `
                <div class="text-center mt-3">
                    <a href="${this.escapeHtml(trackUrl)}" target="_blank" rel="noopener noreferrer" class="track-live-btn">
                        <i class="bi bi-broadcast"></i> Track Live on FlightAware
                    </a>
                </div>`;
        }

        div.innerHTML = `
            <div class="flight-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="flight-number">${this.escapeHtml(segment.flight_number)}</span>
                    ${segment.airline_name ? `<span class="text-muted ms-2">${this.escapeHtml(segment.airline_name)}</span>` : ''}
                </div>
                <div class="flight-duration">
                    <i class="bi bi-clock"></i> ${duration}
                </div>
            </div>
            <div class="flight-card-body">
                <div class="row align-items-center">
                    <div class="col-5 col-md-4 text-center">
                        <div class="airport-code">${this.escapeHtml(segment.departure_airport)}</div>
                        <div class="airport-name">${this.escapeHtml(segment.departure_airport_name || segment.departure_airport)}</div>
                        <div class="flight-time mt-2">${depTime}</div>
                        <div class="flight-date">${depDate}</div>
                        <div class="countdown mt-1" data-countdown="${segment.scheduled_departure}"></div>
                    </div>
                    <div class="col-2 col-md-4 text-center">
                        <i class="bi bi-airplane fs-2 text-primary"></i>
                    </div>
                    <div class="col-5 col-md-4 text-center">
                        <div class="airport-code">${this.escapeHtml(segment.arrival_airport)}</div>
                        <div class="airport-name">${this.escapeHtml(segment.arrival_airport_name || segment.arrival_airport)}</div>
                        <div class="flight-time mt-2">${arrTime}</div>
                        <div class="flight-date">${arrDate}</div>
                    </div>
                </div>
                ${trackingHtml}
            </div>
        `;

        return div;
    }

    renderPastTrips() {
        const container = document.getElementById('past-trips-list');
        if (!container || this.pastTrips.length === 0) return;

        container.innerHTML = this.pastTrips.map(trip => `
            <div class="past-trip-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">${this.escapeHtml(trip.name)}</h6>
                        <small class="text-muted">
                            <i class="bi bi-geo-alt"></i> ${this.escapeHtml(trip.destination_city)}, ${this.escapeHtml(trip.destination_country)}
                        </small>
                    </div>
                    <small class="text-muted">
                        ${this.formatMonthYear(trip.start_date)}
                    </small>
                </div>
                ${trip.total_segments > 0 ? `
                    <small class="text-muted d-block mt-2">
                        <i class="bi bi-airplane"></i> ${trip.total_segments} flight${trip.total_segments > 1 ? 's' : ''}
                    </small>
                ` : ''}
            </div>
        `).join('');
    }

    renderNoTrip() {
        const container = document.getElementById('current-trip-container');
        if (container) {
            container.innerHTML = `
                <div class="no-trip-message">
                    <i class="bi bi-calendar-x fs-1 text-muted"></i>
                    <h2 class="h4 mt-3">No Upcoming Trips</h2>
                    <p class="text-muted">Check back soon for the next adventure!</p>
                </div>
            `;
        }
    }

    startCountdowns() {
        // Clear any existing intervals
        this.countdownIntervals.forEach(interval => clearInterval(interval));
        this.countdownIntervals = [];

        // Update countdowns every second
        const updateAll = () => {
            document.querySelectorAll('[data-countdown]').forEach(el => {
                this.updateCountdown(el);
            });
            this.updateMainCountdown();
        };

        updateAll();
        this.countdownIntervals.push(setInterval(updateAll, 1000));
    }

    updateCountdown(element) {
        const target = new Date(element.dataset.countdown);
        const now = new Date();
        const diff = target - now;

        if (diff <= 0) {
            element.textContent = 'Departed';
            element.classList.add('text-success');
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        let text = '';
        if (days > 0) text += `${days}d `;
        if (hours > 0 || days > 0) text += `${hours}h `;
        text += `${minutes}m ${seconds}s`;

        element.textContent = text;
    }

    updateMainCountdown() {
        const mainCountdown = document.getElementById('main-countdown');
        if (!mainCountdown || !this.currentTrip) return;

        // Get first departure segment
        const depDir = this.currentTrip.directions?.departure;
        if (!depDir || !depDir.segments || depDir.segments.length === 0) return;

        const firstSegment = depDir.segments[0];
        const target = new Date(firstSegment.scheduled_departure);
        const now = new Date();
        const diff = target - now;

        if (diff <= 0) {
            mainCountdown.textContent = 'In Progress!';
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        mainCountdown.textContent = `${days}d ${hours}h`;
    }

    // Utility methods
    formatTime(datetime) {
        const date = new Date(datetime);
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }

    formatDate(datetime) {
        const date = new Date(datetime);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    formatMonthYear(datetime) {
        const date = new Date(datetime);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            year: 'numeric'
        });
    }

    formatDuration(minutes) {
        if (!minutes || minutes <= 0) return '—';
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        if (hours > 0) {
            return `${hours}h ${mins}m`;
        }
        return `${mins}m`;
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showError(message) {
        console.error(message);
        const container = document.getElementById('current-trip-container');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger text-center">
                    <i class="bi bi-exclamation-triangle"></i> ${this.escapeHtml(message)}
                </div>
            `;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if we're on a page that uses JavaScript rendering
    // The main index.php uses server-side rendering, so this is optional
    if (document.getElementById('departure-segments') || document.getElementById('return-segments')) {
        new FlightTracker();
    }
});
