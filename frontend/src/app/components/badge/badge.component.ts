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
              width: 100%;
              max-width: 380px;
              margin: 40px auto;
              padding: 25px;
              border-radius: 16px;
              background: white;
              border: 2px solid #2563eb;
              box-shadow: 0 8px 20px rgba(0,0,0,0.12);
              display: flex;
              flex-direction: column;
              align-items: center;
              text-align: center;
            }

            .badge-header {
              font-size: 20px;
              font-weight: 700;
              color: #2563eb;
              margin-bottom: 20px;
            }

            .participant-name {
              font-size: 18px;
              font-weight: 600;
              margin: 10px 0;
            }

            .qr-code {
              margin: 20px 0;
            }

            .qr-code img {
              max-width: 180px;
              height: auto;
              border: 2px solid #2563eb;
              border-radius: 8px;
              padding: 8px;
              background: #fff;
            }

            .instructions {
              font-size: 12px;
              color: #555;
              margin-top: 10px;
            }

            .footer {
              margin-top: 15px;
              font-size: 10px;
              color: #888;
            }

            @media print {
              body { background: none; }
              .badge-container {
                box-shadow: none;
                border: 2px solid #2563eb;
                page-break-inside: avoid;
              }
            }
          </style>
        </head>
        <body>
          <div class="badge-container">
            <div class="badge-header">Event Access Badge</div>
            <div class="participant-name">${this.participantGender}</div>
            <div class="participant-name">${this.participantName}</div>
             <div class="participant-name">${this.acessType}</div>
            <div class="qr-code">
              <img src="data:image/png;base64,${this.qrCode}" alt="QR Code" />
            </div>
            <div class="instructions">
              Show this QR code at the event entrance
            </div>
            <div class="footer">
              Generated on ${new Date().toLocaleDateString('en-GB')}
            </div>
          </div>
        </body>
      </html>
    `);
      printWindow.document.close();
      printWindow.focus();
      printWindow.print();
    }
  }


  registerAnother(): void {
    this.router.navigate(['/register']);
  }
}
