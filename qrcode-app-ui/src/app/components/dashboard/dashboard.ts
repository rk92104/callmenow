import { Component, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PersonService } from '../../services/person.service';
import { Person } from '../../models/person.model';

@Component({
    selector: 'app-dashboard',
    standalone: true,
    imports: [CommonModule],
    template: `
    <div class="dashboard-header">
      <h1>CallMeNow <span class="gradient-text">Console</span></h1>
      <p class="subtitle">Owners linked after activation · Inventory encodes scan URLs, not phone numbers</p>
    </div>

    <div class="stats-grid">
      <!-- Total Card -->
      <div class="stat-card">
        <div class="icon-orb total">👥</div>
        <div class="stat-info">
          <label>Total owners</label>
          <h3>{{ totalCount() }}</h3>
          <span class="growth">Overall entries</span>
        </div>
      </div>

      <!-- Today Card -->
      <div class="stat-card">
        <div class="icon-orb today">📅</div>
        <div class="stat-info">
          <label>New today</label>
          <h3>{{ todayCount() }}</h3>
          <span class="growth">Activations / adds</span>
        </div>
      </div>

      <!-- Week Card -->
      <div class="stat-card">
        <div class="icon-orb week">🗓️</div>
        <div class="stat-info">
          <label>This Week</label>
          <h3>{{ weekCount() }}</h3>
          <span class="growth">Last 7 days</span>
        </div>
      </div>

      <!-- Month Card -->
      <div class="stat-card">
        <div class="icon-orb month">📊</div>
        <div class="stat-info">
          <label>This Month</label>
          <h3>{{ monthCount() }}</h3>
          <span class="growth">Last 30 days</span>
        </div>
      </div>
    </div>

    <div class="recent-activity">
      <h2>Recent <span class="gradient-text">Additions</span></h2>
      <div class="activity-list">
        @for (person of recentPersons(); track person.id) {
          <div class="activity-item">
            <div class="avatar-small">{{ person.name.charAt(0).toUpperCase() }}</div>
            <div class="item-info">
              <p class="item-name">{{ person.name }}</p>
              <p class="item-meta">{{ person.phoneNumber }} • {{ person.createdAt | date:'shortTime' }}</p>
            </div>
            <div class="item-date">{{ person.createdAt | date:'mediumDate' }}</div>
          </div>
        } @empty {
          <p class="empty-msg">No recent activity found.</p>
        }
      </div>
    </div>
  `,
    styles: [`
    .dashboard-header {
      margin-bottom: 40px;
    }

    .dashboard-header h1 {
      font-size: clamp(1.65rem, 5vw, 2.5rem);
      font-weight: 800;
      color: var(--cmn-heading);
      margin-bottom: 8px;
      line-height: 1.15;
    }

    .gradient-text {
      background: linear-gradient(135deg, #6366f1, #7c3aed 50%, #a855f7);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .subtitle {
      color: var(--cmn-text-muted);
      font-size: clamp(0.95rem, 2.5vw, 1.1rem);
      line-height: 1.5;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(min(100%, 240px), 1fr));
      gap: clamp(14px, 3vw, 24px);
      margin-bottom: clamp(28px, 5vw, 50px);
    }

    .stat-card {
      background: var(--cmn-card-bg);
      border: 1px solid var(--cmn-card-border);
      border-radius: 24px;
      padding: clamp(18px, 4vw, 30px);
      display: flex;
      align-items: center;
      gap: 24px;
      backdrop-filter: blur(10px);
      box-shadow: var(--cmn-panel-shadow, 0 4px 24px rgba(99, 102, 241, 0.06));
      transition: all 0.3s ease;
    }

    html[data-theme='light'] .stat-card {
      background: var(--cmn-card-bg-solid);
    }

    .stat-card:hover {
      transform: translateY(-5px);
      border-color: rgba(99, 102, 241, 0.35);
      filter: brightness(1.02);
    }

    html[data-theme="dark"] .stat-card:hover {
      background: rgba(30, 30, 50, 0.75);
      filter: none;
    }

    .icon-orb {
      width: 64px;
      height: 64px;
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
    }

    .icon-orb.total { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
    .icon-orb.today { background: rgba(0, 200, 83, 0.1); color: #00c853; }
    .icon-orb.week { background: rgba(251, 191, 36, 0.1); color: #fbbf24; }
    .icon-orb.month { background: rgba(239, 68, 68, 0.1); color: #f87171; }

    .stat-info label {
      display: block;
      color: var(--cmn-label);
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-bottom: 4px;
    }

    .stat-info h3 {
      font-size: clamp(1.75rem, 5vw, 2.2rem);
      font-weight: 900;
      color: var(--cmn-heading);
      margin-bottom: 4px;
    }

    .growth {
      font-size: 0.8rem;
      color: var(--cmn-text-muted);
    }

    .recent-activity {
      background: var(--cmn-card-bg);
      border: 1px solid var(--cmn-card-border);
      border-radius: 24px;
      padding: clamp(18px, 4vw, 32px);
      box-shadow: var(--cmn-panel-shadow, 0 4px 24px rgba(99, 102, 241, 0.06));
    }

    html[data-theme='light'] .recent-activity {
      background: var(--cmn-card-bg-solid);
    }

    .recent-activity h2 {
      font-size: clamp(1.2rem, 3.5vw, 1.5rem);
      font-weight: 800;
      color: var(--cmn-heading);
      margin-bottom: 24px;
    }

    .activity-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .activity-item {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 14px 16px;
      background: var(--cmn-elevated);
      border-radius: 16px;
      border: 1px solid transparent;
      transition: background 0.2s ease, border-color 0.2s ease;
    }

    .activity-item:hover {
      border-color: rgba(99, 102, 241, 0.15);
      filter: brightness(0.99);
    }

    html[data-theme='dark'] .activity-item {
      background: rgba(15, 23, 42, 0.45);
    }

    html[data-theme='dark'] .activity-item:hover {
      background: rgba(15, 23, 42, 0.65);
      filter: none;
    }

    .avatar-small {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, #6366f1, #a855f7);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      color: white;
    }

    .item-info {
      flex: 1;
    }

    .item-name {
      font-weight: 700;
      color: var(--cmn-heading);
      margin-bottom: 2px;
    }

    .item-meta {
      font-size: 0.85rem;
      color: var(--cmn-text-muted);
    }

    .item-date {
      font-size: 0.85rem;
      color: var(--cmn-text-muted);
      font-weight: 600;
      padding: 4px 12px;
      background: rgba(99, 102, 241, 0.1);
      border-radius: 8px;
    }

    .empty-msg {
      text-align: center;
      padding: 40px;
      color: var(--cmn-text-muted);
      font-style: italic;
    }

    @media (max-width: 640px) {
      .dashboard-header {
        margin-bottom: 24px;
      }

      .stat-card {
        flex-direction: row;
        flex-wrap: wrap;
      }

      .icon-orb {
        width: 52px;
        height: 52px;
        font-size: 1.5rem;
      }

      .activity-item {
        flex-wrap: wrap;
      }

      .item-date {
        width: 100%;
        margin-left: 56px;
      }
    }
  `]
})
export class DashboardComponent implements OnInit {
    persons = signal<Person[]>([]);

    constructor(private readonly personService: PersonService) { }

    ngOnInit() {
        this.personService.getAll().subscribe(data => this.persons.set(data));
    }

    totalCount = computed(() => this.persons().length);

    todayCount = computed(() => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return this.persons().filter(p => new Date(p.createdAt) >= today).length;
    });

    weekCount = computed(() => {
        const weekAgo = new Date();
        weekAgo.setDate(weekAgo.getDate() - 7);
        return this.persons().filter(p => new Date(p.createdAt) >= weekAgo).length;
    });

    monthCount = computed(() => {
        const monthAgo = new Date();
        monthAgo.setDate(monthAgo.getDate() - 30);
        return this.persons().filter(p => new Date(p.createdAt) >= monthAgo).length;
    });

    recentPersons = computed(() => {
        return [...this.persons()].sort((a, b) =>
            new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
        ).slice(0, 5);
    });
}
