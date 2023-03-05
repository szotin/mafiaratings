export interface GameSnapshot {
  game?: Game;
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
  nominatedPlayers: Player[];
  legacy: number[];
  legacyPlayers: Player[];
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
  isSpeaking: boolean;
  gender: Gender;
  photoUrl: string;
  hasPhoto: boolean;
  role: PlayerRole;
  warnings: number;
  state: PlayerState;
  deathRound?: number;
  deathType?: DeathType;
  checkedBySheriff?: number;
  checkedByDon?: number;
}

export enum PlayerRole {
  none = '',
  maf = 'maf',
  don = 'don',
  town = 'town',
  sheriff = 'sheriff',
}

export enum Gender {
  none = '',
  male = 'male',
  female = 'female',
}

export enum DeathType {
  none = '',
  kickOut = 'kickOut',
  warnings = 'warnings',
  shooting = 'shooting',
  voted = 'voting'
}

export enum PlayerState {
  alive = 'alive',
  dead = 'dead',
}
