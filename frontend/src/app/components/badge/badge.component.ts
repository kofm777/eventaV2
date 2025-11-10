import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

@Component({
  selector: 'app-badge',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './badge.component.html',
  styleUrls: ['./badge.component.css']
})
export class BadgeComponent implements OnInit {
  qrCode: string | null = null;
  participantName: string | null = null;
  emailSent = true;

  constructor(private router: Router) {}

  ngOnInit(): void {
    const navigation = this.router.getCurrentNavigation();
    if (navigation?.extras?.state) {
      this.qrCode = navigation.extras.state['qrCode'];
      this.participantName = navigation.extras.state['participantName'];
      this.emailSent = navigation.extras.state['emailSent'] ?? true;
    }

    if (!this.qrCode) {
      // If no data, redirect to register
      this.router.navigate(['/register']);
    }
  }

  downloadQR(): void {
    if (!this.qrCode) return;

    const link = document.createElement('a');
    link.href = `data:image/png;base64,${this.qrCode}`;
    link.download = `qr-code-${Date.now()}.png`;
    link.click();
  }

  printQR(): void {
    if (!this.qrCode) return;

    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.write(`
        <html>
          <head>
            <title>QR Code - Event Access</title>
            <style>
              body { text-align: center; font-family: Arial, sans-serif; padding: 20px; }
              h1 { color: #2563eb; }
              img { max-width: 400px; margin: 20px 0; }
            </style>
          </head>
          <body>
            <h1>Event Access - QR Code</h1>
            <p><strong>${this.participantName}</strong></p>
            <img src="data:image/png;base64,${this.qrCode}" alt="QR Code" />
            <p>Présentez ce QR code à l'entrée de l'événement</p>
          </body>
        </html>
      `);
      printWindow.document.close();
      printWindow.print();
    }
  }

  registerAnother(): void {
    this.router.navigate(['/register']);
  }
}
