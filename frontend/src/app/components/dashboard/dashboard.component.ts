import { Component, OnInit, AfterViewInit, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '../../services/api.service';
import { Router } from '@angular/router';
import Chart from 'chart.js/auto';

@Component({
    selector: 'app-dashboard',
    standalone: true,
    imports: [CommonModule],
    templateUrl: './dashboard.component.html',
    styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit, AfterViewInit {
    stats: any = null;
    loading = true;
    error = '';

    // Chart references
    @ViewChild('statusChart') statusChartRef!: ElementRef;
    @ViewChild('registrationChart') registrationChartRef!: ElementRef;
    @ViewChild('scanChart') scanChartRef!: ElementRef;

    charts: any = {};
    registrationView: 'daily' | 'weekly' = 'daily';

    constructor(private apiService: ApiService, private router: Router) { }

    ngOnInit() {
        this.loadStats();
        // Auto refresh every 30s
        setInterval(() => {
            this.loadStats(true);
        }, 30000);
    }

    ngAfterViewInit() {
        // Charts will be initialized after data load
    }

    loadStats(silent = false) {
        if (!silent) this.loading = true;

        this.apiService.getDashboardStats().subscribe({
            next: (res) => {
                if (res.ok) {
                    this.stats = res.stats;
                    if (!silent) this.loading = false;
                    setTimeout(() => this.initCharts(), 0); // Wait for DOM
                } else {
                    this.error = 'Failed to load stats';
                    this.loading = false;
                }
            },
            error: (err) => {
                this.error = 'Error loading stats';
                this.loading = false;
                console.error(err);
            }
        });
    }

    toggleRegistrationView(view: 'daily' | 'weekly') {
        this.registrationView = view;
        this.initRegistrationChart();
    }

    initCharts() {
        if (!this.stats) return;
        this.initStatusChart();
        this.initRegistrationChart();
        this.initScanChart();
    }

    initStatusChart() {
        if (this.charts.status) this.charts.status.destroy();

        const ctx = this.statusChartRef.nativeElement.getContext('2d');
        const data = this.stats.charts.status_distribution;

        this.charts.status = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Accepted', 'Pending', 'Rejected'],
                datasets: [{
                    data: [
                        data['accepted'] || 0,
                        data['pending'] || 0,
                        data['rejected'] || 0
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    initRegistrationChart() {
        if (this.charts.registration) this.charts.registration.destroy();

        const ctx = this.registrationChartRef.nativeElement.getContext('2d');
        const isDaily = this.registrationView === 'daily';
        const rawData = isDaily ? this.stats.charts.daily_registrations : this.stats.charts.weekly_registrations;

        const labels = rawData.map((d: any) => isDaily ? d.date : `Week ${d.week}`);
        const values = rawData.map((d: any) => d.count);

        this.charts.registration = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'New Participants',
                    data: values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    initScanChart() {
        if (this.charts.scan) this.charts.scan.destroy();

        const ctx = this.scanChartRef.nativeElement.getContext('2d');
        const dailyScans = this.stats.charts.daily_scans;

        // Extract dates and types
        const dates = Object.keys(dailyScans);
        const fairData = dates.map(date => {
            const dayData = dailyScans[date];
            const fair = dayData.find((d: any) => d.scan_type === 'fair');
            return fair ? fair.count : 0;
        });
        const confData = dates.map(date => {
            const dayData = dailyScans[date];
            const conf = dayData.find((d: any) => d.scan_type === 'fair + conference');
            return conf ? conf.count : 0;
        });

        this.charts.scan = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Fair Scans',
                        data: fairData,
                        backgroundColor: '#f59e0b'
                    },
                    {
                        label: 'Conference Scans',
                        data: confData,
                        backgroundColor: '#2563eb'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });
    }
}
