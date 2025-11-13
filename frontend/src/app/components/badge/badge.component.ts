import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {Router, RouterLink} from '@angular/router';

@Component({
  selector: 'app-badge',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './badge.component.html',
  styleUrls: ['./badge.component.css']
})
export class BadgeComponent implements OnInit {
  qrCode: string | null = null;
  participantName: string | null = null;
  acessType: string | null = null;
participantGender: string | null = null;
  emailSent = true;

  constructor(private router: Router) {}

ngOnInit(): void {
  const state = history.state;
  if (state?.qrCode) {
    this.qrCode = state.qrCode;
    this.participantGender = state.participantGender;
    this.acessType = state.accessType;
    this.participantName = state.participantName;
    this.emailSent = state.emailSent ?? true;
  } else {
    console.error('No QR code in navigation state — redirecting to register');
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

    const src = this.qrCode.startsWith('data:image')
        ? this.qrCode
        : `data:image/png;base64,${this.qrCode}`;

    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.write(`
      <html>
        <head>
          <title>Badge - Event Access</title>
          <style>
            body {
              margin: 0;
              padding: 0;
              font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
              background: #f3f4f6;
            }
            .badge-container {
              max-width: 380px;
              margin: 40px auto;
              padding: 25px;
              border-radius: 16px;
              background: white;
              border: 2px solid #2563eb;
              box-shadow: 0 8px 20px rgba(0,0,0,0.12);
              text-align: center;
            }
            .qr-code img {
              max-width: 180px;
              border: 2px solid #2563eb;
              border-radius: 8px;
              padding: 8px;
              background: #fff;
            }
            @media print {
              body { background: none; }
              .badge-container { box-shadow: none; }
            }
          </style>
        </head>
        <body>
          <div class="badge-container">
            <h2>Event Access Badge</h2>
            <div>${this.participantGender}</div>
            <div>${this.participantName}</div>
            <div>${this.acessType}</div>
            <div class="qr-code">
              <img src="${src}" alt="QR Code" />
            </div>
            <p>Show this QR code at the event entrance</p>
          </div>
        </body>
      </html>
    `);

      printWindow.document.close();

      const img = printWindow.document.querySelector('img');
      if (img) {
        img.addEventListener('load', () => {
          printWindow.focus();
          printWindow.print();
        });
      } else {
        setTimeout(() => {
          printWindow.focus();
          printWindow.print();
        }, 500);
      }
    }
  }



  registerAnother(): void {
    this.router.navigate(['/register']);
  }
}
