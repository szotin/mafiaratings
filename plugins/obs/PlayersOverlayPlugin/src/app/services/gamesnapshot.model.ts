export interface GameSnapshot {
  game: Game;
  version: number;
}

export interface Game {
  id: number;
  name: string;
  phase: GamePhase;
  state: GameState;
  round: number;
  players: Player[];
  nominees: number[];
}

export enum GamePhase {
  night = 'night',
  day = 'day',
}
export enum GameState {
  starting = 'starting',
  notStarted = 'notStarted',
  arranging = 'arranging'
}

export interface Player {
  id: number;
  name: string;
  number: number;
  photoUrl: string;
  role: PlayerRole;
  warnings: number;
  state: PlayerState;
  isSpeaking: boolean;
  hasPhoto: boolean;
}

export enum PlayerRole {
  none = '',
  maf = 'maf',
  don = 'don',
  town = 'town',
  sheriff = 'sheriff',
}

export enum PlayerState {
  alive = 'alive',
  dead = 'dead',
}
