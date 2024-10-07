export interface GameSnapshot {
  game?: Game;
  version: number;
}

export interface Game {
  id: number;
  club: Club;
  tournament: Tournament;
  name: string;
  stage: number;
  tour: number;
  phase: GamePhase;
  state: GameState;
  round: number;
  moderator: Referee;
  players: Player[];
  nominees: number[];
  nominatedPlayers: Player[];
  legacy: number[];
  legacyPlayers: Player[];
}

export interface Club {
  name : string;
  iconUrl: string;
}

export interface Tournament {
  name : string;
  iconUrl: string;
}

export enum Stage {
  default = 'round',
  quals = 'round',
  finals = 'finals',
  semis = 'semis',
  quarters = 'quarters'
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

export interface Referee {
  id: number;
  name: string;
  gender: Gender;
  photoUrl: string;
  hasPhoto: boolean;
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
