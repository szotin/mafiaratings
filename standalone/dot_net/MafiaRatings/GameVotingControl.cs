using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Drawing;
using System.Data;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using MafiaRatings.GameObjects;

namespace MafiaRatings
{
    public partial class GameVotingControl : GameControl
    {
        private struct PlayerControls
        {
            public BorderLabel nameLabel;
            public GameButtons buttons;
            public TimerControl timer;
            public BorderCheckBox vote;
            public BorderLabel votesLabel;
            public BorderLabel numberLabel;
        }

        private PlayerControls[] mPlayerControls;

        private void InitPlayerControls()
        {
            mPlayerControls = new PlayerControls[10];

            mPlayerControls[0].nameLabel = nameLabel1;
            mPlayerControls[0].buttons = gameButtons1;
            mPlayerControls[0].timer = timerControl1;
            mPlayerControls[0].vote = voteCheckBox1;
            mPlayerControls[0].votesLabel = votesLabel1;
            mPlayerControls[0].numberLabel = numberLabel1;

            mPlayerControls[1].nameLabel = nameLabel2;
            mPlayerControls[1].buttons = gameButtons2;
            mPlayerControls[1].timer = timerControl2;
            mPlayerControls[1].vote = voteCheckBox2;
            mPlayerControls[1].votesLabel = votesLabel2;
            mPlayerControls[1].numberLabel = numberLabel2;

            mPlayerControls[2].nameLabel = nameLabel3;
            mPlayerControls[2].buttons = gameButtons3;
            mPlayerControls[2].timer = timerControl3;
            mPlayerControls[2].vote = voteCheckBox3;
            mPlayerControls[2].votesLabel = votesLabel3;
            mPlayerControls[2].numberLabel = numberLabel3;

            mPlayerControls[3].nameLabel = nameLabel4;
            mPlayerControls[3].buttons = gameButtons4;
            mPlayerControls[3].timer = timerControl4;
            mPlayerControls[3].vote = voteCheckBox4;
            mPlayerControls[3].votesLabel = votesLabel4;
            mPlayerControls[3].numberLabel = numberLabel4;

            mPlayerControls[4].nameLabel = nameLabel5;
            mPlayerControls[4].buttons = gameButtons5;
            mPlayerControls[4].timer = timerControl5;
            mPlayerControls[4].vote = voteCheckBox5;
            mPlayerControls[4].votesLabel = votesLabel5;
            mPlayerControls[4].numberLabel = numberLabel5;

            mPlayerControls[5].nameLabel = nameLabel6;
            mPlayerControls[5].buttons = gameButtons6;
            mPlayerControls[5].timer = timerControl6;
            mPlayerControls[5].vote = voteCheckBox6;
            mPlayerControls[5].votesLabel = votesLabel6;
            mPlayerControls[5].numberLabel = numberLabel6;

            mPlayerControls[6].nameLabel = nameLabel7;
            mPlayerControls[6].buttons = gameButtons7;
            mPlayerControls[6].timer = timerControl7;
            mPlayerControls[6].vote = voteCheckBox7;
            mPlayerControls[6].votesLabel = votesLabel7;
            mPlayerControls[6].numberLabel = numberLabel7;

            mPlayerControls[7].nameLabel = nameLabel8;
            mPlayerControls[7].buttons = gameButtons8;
            mPlayerControls[7].timer = timerControl8;
            mPlayerControls[7].vote = voteCheckBox8;
            mPlayerControls[7].votesLabel = votesLabel8;
            mPlayerControls[7].numberLabel = numberLabel8;

            mPlayerControls[8].nameLabel = nameLabel9;
            mPlayerControls[8].buttons = gameButtons9;
            mPlayerControls[8].timer = timerControl9;
            mPlayerControls[8].vote = voteCheckBox9;
            mPlayerControls[8].votesLabel = votesLabel9;
            mPlayerControls[8].numberLabel = numberLabel9;

            mPlayerControls[9].nameLabel = nameLabel10;
            mPlayerControls[9].buttons = gameButtons10;
            mPlayerControls[9].timer = timerControl10;
            mPlayerControls[9].vote = voteCheckBox10;
            mPlayerControls[9].votesLabel = votesLabel10;
            mPlayerControls[9].numberLabel = numberLabel10;
        }

        public GameVotingControl()
        {
            InitializeComponent();
            InitPlayerControls();

            for (int i = 0; i < 10; ++i)
            {
                mPlayerControls[i].vote.String = Properties.Resources.Vote;
            }
        }

        public static bool IsMyGameState(GameState state)
        {
            switch (state)
            {
                case GameState.VotingKilledSpeaking:
                case GameState.Voting:
                case GameState.VotingMultipleWinners:
                case GameState.VotingNominantSpeaking:
                    return true;
            }
            return false;
        }

        public override bool IsActive
        {
            get { return IsMyGameState(Database.Game.State); }
        }

        protected override void OnReload()
        {
            Game game = Database.Game;
            SuspendLayout();
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                PlayerControls controls = mPlayerControls[i];

                controls.nameLabel.String = player.Name;
                controls.buttons.PlayerNum = i;
            }
            ResumeLayout();
        }

        protected override void OnGameStateChange()
        {
            Game game = Database.Game;
            titleLabel.Text = string.Format(Properties.Resources.DayNum, game.Round + 1);

            string tipText = string.Empty;
            int markedPlayer = -1;
            GamePlayer player;

            bool voting = false;
            bool someoneSpeaking = false;
            bool allOrNobodyVisible = false;

            if (game.CurrentVoting.Canceled)
            {
		        tipText = Properties.Resources.VotingCanceled;
            }
            else switch (game.State)
            {
                case GameState.VotingKilledSpeaking:
                    markedPlayer = game.PlayerSpeaking;
                    player = game.Players[markedPlayer];
                    if (game.KillingThisDay)
                    {
                        tipText = string.Format(Properties.Resources.VotingLastSpeech, player, player.IsMale ? Properties.Resources.his : Properties.Resources.her);
                    }
                    else
                    {
                        tipText = string.Format(Properties.Resources.PlayerSpeaking, player);
                    }

                    tipText += Properties.Resources.Space;
                    if (game.CurrentNominant < game.CurrentVoting.Winners.Count - 1)
                    {
                        tipText += string.Format(Properties.Resources.FloorToNext, game.Players[game.CurrentVoting.Winners[game.CurrentNominant + 1]]);
                    }
                    else
                    {
                        tipText += Properties.Resources.StartNight;
                    }
                    someoneSpeaking = true;
                    break;

                case GameState.Voting:
                    markedPlayer = game.CurrentVoting.Nominants[game.CurrentNominant].PlayerNum;
                    player = game.Players[markedPlayer];
                    if (game.CurrentNominant + 2 < game.CurrentVoting.Nominants.Count)
                    {
                        tipText = string.Format(Properties.Resources.VotingFor, player, game.Players[game.CurrentVoting.Nominants[game.CurrentNominant + 1].PlayerNum]);
                    }
                    else
                    {
                        tipText = string.Format(Properties.Resources.VotingFor1, player);
                    }
                    voting = true;
                    break;

                case GameState.VotingMultipleWinners:
                    if (game.NumPlayers == 4 && game.CurrentVoting.Winners.Count == 2 && game.Rules.NoCrash4)
                    {
                        tipText = Properties.Resources.NobodyKilledNoRepeat;
                    }
                    else if (!game.KillingThisDay)
                    {
                        tipText = string.Format(Properties.Resources.ManyDistrusted, game.CurrentVoting.Winners.Count, game.Players[game.CurrentVoting.Winners[0]]);
                    }
                    else
                    {
                        tipText = string.Format(Properties.Resources.ManyVoteLeaders, game.CurrentVoting.Winners.Count);
                        if (game.CurrentVoting.VotingRound <= 0)
                        {
                            tipText += string.Format(Properties.Resources.RepeatVoting, game.Players[game.CurrentVoting.Winners[0]]);
                        }
                        else if (game.NumPlayers == 3)
                        {
                            tipText += Properties.Resources.VotingNobodyKilled;
                        }
                        else
                        {
                            tipText += Properties.Resources.AllOrNobody;
                            allOrNobodyVisible = true;
                        }
                    }
                    break;

                case GameState.VotingNominantSpeaking:
                    {
                        markedPlayer = game.PlayerSpeaking;
                        player = game.Players[markedPlayer];
                        int nom = game.CurrentVoting.GetNominationIndex(markedPlayer) + 1;
                        tipText = string.Format(Properties.Resources.PlayerSpeaking, player) + Properties.Resources.Space;
                        if (nom < game.CurrentVoting.Nominants.Count)
                        {
                            tipText += string.Format(Properties.Resources.FloorToNext, game.Players[game.CurrentVoting.Nominants[nom].PlayerNum]);
                        }
                        else if (game.KillingThisDay)
                        {
                            tipText += Properties.Resources.StartVoting;
                        }
                        else
                        {
                            tipText += Properties.Resources.StartNight;
                        }
                        someoneSpeaking = true;
                    }
                    break;
            }

            for (int i = 0; i < 10; ++i)
            {
                PlayerControls controls = mPlayerControls[i];
                player = game.Players[i];

                int color;
                if (markedPlayer == i)
                {
                    color = HIGHLIGHT;
                }
                else if (player.IsAlive)
                {
                    color = ALIVE;
                }
                else
                {
                    color = DEAD;
                }

                if (game.CurrentVoting.IsNominated(i))
                {
                    SetControlState(controls.votesLabel, HIGHLIGHT, true);
                }
                else
                {
                    SetControlState(controls.votesLabel, color, true);
                }

                bool voteEnabled = false;
                if (player.IsAlive)
                {
                    voteEnabled = voting;
                    if (voteEnabled)
                    {
                        int votesFor = game.CurrentVoting.Votes[i];
                        if (votesFor == game.CurrentNominant)
                        {
                            controls.vote.Checked = true;
                        }
                        else if (votesFor < 0 || votesFor > game.CurrentNominant)
                        {
                            controls.vote.Checked = false;
                        }
                        else
                        {
                            voteEnabled = false;
                        }
                    }
                }

                SetControlState(controls.buttons, color, player.IsAlive);
                SetControlState(controls.vote, color, voteEnabled);
                SetControlState(controls.timer, color, someoneSpeaking && game.PlayerSpeaking == i);
                SetControlState(controls.numberLabel, color, true);
                SetControlState(controls.nameLabel, color, true);

                int nominationIndex;
                if (!game.CurrentVoting.Canceled && (nominationIndex = game.CurrentVoting.GetNominationIndex(i)) >= 0)
                {
                    controls.votesLabel.String = game.CurrentVoting.Nominants[nominationIndex].Count.ToString();
                }
                else
                {
                    controls.votesLabel.String = string.Empty;
                }
            }

            allButton.Visible = nobodyButton.Visible = allOrNobodyVisible;
            tipLabel.Text = tipText;
        }

        private void voteCheckBox_CheckedChanged(object sender, EventArgs e)
        {
            if (Initializing)
            {
                return;
            }

            BorderCheckBox box = null;
            int playerNum;
            for (playerNum = 0; playerNum < 10; ++playerNum)
            {
                PlayerControls controls = mPlayerControls[playerNum];
                if (controls.vote.IsMyEvent(sender))
                {
                    box = controls.vote;
                    break;
                }
            }

            Game game = Database.Game;
            if (box != null)
            {
                game.Vote(playerNum, box.Checked);

                foreach (GameVoting.Nominant nominant in game.CurrentVoting.Nominants)
                {
                    mPlayerControls[nominant.PlayerNum].votesLabel.String = nominant.Count.ToString();
                }
            }
        }

        private void allButton_CheckedChanged(object sender, EventArgs e)
        {
            Database.Game.CurrentVoting.MultipleKill = allButton.Checked;
        }
    }
}
