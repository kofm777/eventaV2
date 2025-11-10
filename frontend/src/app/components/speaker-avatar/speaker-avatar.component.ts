import { Component, Input, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-speaker-avatar',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './speaker-avatar.component.html',
  styleUrls: ['./speaker-avatar.component.css']
})
export class SpeakerAvatarComponent implements OnInit, OnDestroy {
  @Input() participantName: string = '';
  @Input() gender: string = 'Femme';
  @Input() autoPlay: boolean = true;
  @Input() accessStatus: string = 'Accepted'; // 'Accepted' or 'Denied'
  @Input() accessType: string = 'both'; // 'foire', 'conference', 'both'

  private synth: SpeechSynthesis;
  private utterance: SpeechSynthesisUtterance | null = null;

  isPlaying = false;
  isSpeaking = false;
  videoReady = false;
  error: string | null = null;

  // TTS Configuration
  private frenchVoices: SpeechSynthesisVoice[] = [];
  private selectedVoice: SpeechSynthesisVoice | null = null;

  constructor() {
    this.synth = window.speechSynthesis;
  }

  ngOnInit() {
    this.loadVoices();

    // Load voices when they become available
    if (speechSynthesis.onvoiceschanged !== undefined) {
      speechSynthesis.onvoiceschanged = () => {
        this.loadVoices();
      };
    }

    if (this.autoPlay && this.participantName) {
      // Delay auto-play to ensure voices are loaded
      setTimeout(() => {
        this.playWelcomeMessage();
      }, 500);
    }
  }

  ngOnDestroy() {
    this.stopSpeaking();
  }

  loadVoices() {
    const voices = this.synth.getVoices();

    // Filter French voices
    this.frenchVoices = voices.filter(voice =>
      voice.lang.startsWith('fr') || voice.lang.startsWith('FR')
    );

    // Select a female French voice if available
    this.selectedVoice = this.frenchVoices.find(voice =>
      voice.name.toLowerCase().includes('female') ||
      voice.name.toLowerCase().includes('femme') ||
      voice.name.toLowerCase().includes('amelie') ||
      voice.name.toLowerCase().includes('thomas')
    ) || this.frenchVoices[0] || voices[0];

    console.log('Available French voices:', this.frenchVoices);
    console.log('Selected voice:', this.selectedVoice);
  }

  playWelcomeMessage() {
    if (!this.participantName) {
      this.error = 'Aucun nom de participant fourni.';
      return;
    }

    this.stopSpeaking();

    const message = this.generateWelcomeMessage();
    this.speak(message);
  }

  generateWelcomeMessage(): string {
    const firstName = this.participantName.split(' ')[0];
    const honorific = this.getHonorific();

    const statusMessage = this.accessStatus === 'Accepted'
      ? 'Accès autorisé. Bienvenue à notre événement.'
      : 'Accès refusé. Veuillez contacter l\'administration.';

    const accessTypeMessage = this.getAccessTypeMessage();

    return `${honorific} ${firstName}. ${statusMessage} ${accessTypeMessage}`;
  }

  private getHonorific(): string {
    if (this.gender === 'Homme') {
      return 'Monsieur';
    } else if (this.gender === 'Femme') {
      return 'Madame';
    } else {
      return 'Cher participant';
    }
  }

  private getAccessTypeMessage(): string {
    switch (this.accessType) {
      case 'foire':
        return 'Vous avez accès à la foire uniquement.';
      case 'conference':
        return 'Vous avez accès à la conférence uniquement.';
      case 'both':
        return 'Vous avez accès à la foire et à la conférence.';
      default:
        return '';
    }
  }

  speak(text: string) {
    if (!this.synth) {
      this.error = 'La synthèse vocale n\'est pas supportée par votre navigateur.';
      return;
    }

    this.utterance = new SpeechSynthesisUtterance(text);

    // Configure utterance
    this.utterance.lang = 'fr-FR';
    this.utterance.rate = 0.9; // Slightly slower for clarity
    this.utterance.pitch = 1.0;
    this.utterance.volume = 1.0;

    if (this.selectedVoice) {
      this.utterance.voice = this.selectedVoice;
    }

    // Event handlers
    this.utterance.onstart = () => {
      this.isSpeaking = true;
      this.isPlaying = true;
      this.error = null;
    };

    this.utterance.onend = () => {
      this.isSpeaking = false;
      this.isPlaying = false;
    };

    this.utterance.onerror = (event) => {
      console.error('Speech synthesis error:', event);
      this.error = 'Erreur lors de la lecture du message.';
      this.isSpeaking = false;
      this.isPlaying = false;
    };

    // Speak
    this.synth.speak(this.utterance);
  }

  stopSpeaking() {
    if (this.synth) {
      this.synth.cancel();
      this.isSpeaking = false;
      this.isPlaying = false;
    }
  }

  replay() {
    this.playWelcomeMessage();
  }

  getAccessTypeDisplay(): string {
    switch (this.accessType) {
      case 'foire':
        return 'Foire uniquement';
      case 'conference':
        return 'Conférence uniquement';
      case 'both':
        return 'Foire et Conférence';
      default:
        return '';
    }
  }

  // Video placeholder methods (for future video integration)
  onVideoReady() {
    this.videoReady = true;
  }

  onVideoError() {
    this.error = 'Erreur lors du chargement de la vidéo.';
  }
}
