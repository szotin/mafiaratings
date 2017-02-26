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
    public partial class GameDayControl : GameControl
    {
        private struct PlayerControls
        {
            public BorderLabel nameLabel;
            public GameButtons buttons;
            public TimerControl timer;
            public BorderRadioButton nomination;
            public BorderLabel numberLabel;
        }

        private PlayerControls[] mPlayerControls;

        private void InitPlayerControls()
        {
            mPlayerControls = new PlayerControls[10];

            mPlayerControls[0].nameLabel = nameLabel1;
            mPlayerControls[0].buttons = gameButtons1;
            mPlayerControls[0].timer = timerControl1;
            mPlayerControls[0].nomination = nominateButton1;
            mPlayerControls[0].numberLabel = numberLabel1;

            mPlayerControls[1].nameLabel = nameLabel2;
            mPlayerControls[1].buttons = gameButtons2;
            mPlayerControls[1].timer = timerControl2;
            mPlayerControls[1].nomination = nominateButton2;
            mPlayerControls[1].numberLabel = numberLabel2;

            mPlayerControls[2].nameLabel = nameLabel3;
            mPlayerControls[2].buttons = gameButtons3;
            mPlayerControls[2].timer = timerControl3;
            mPlayerControls[2].nomination = nominateButton3;
            mPlayerControls[2].numberLabel = numberLabel3;

            mPlayerControls[3].nameLabel = nameLabel4;
            mPlayerControls[3].buttons = gameButtons4;
            mPlayerControls[3].timer = timerControl4;
            mPlayerControls[3].nomination = nominateButton4;
            mPlayerControls[3].numberLabel = numberLabel4;

            mPlayerControls[4].nameLabel = nameLabel5;
            mPlayerControls[4].buttons = gameButtons5;
            mPlayerControls[4].timer = timerControl5;
            mPlayerControls[4].nomination = nominateButton5;
            mPlayerControls[4].numberLabel = numberLabel5;

            mPlayerControls[5].nameLabel = nameLabel6;
            mPlayerControls[5].buttons = gameButtons6;
            mPlayerControls[5].timer = timerControl6;
            mPlayerControls[5].nomination = nominateButton6;
            mPlayerControls[5].numberLabel = numberLabel6;

            mPlayerControls[6].nameLabel = nameLabel7;
            mPlayerControls[6].buttons = gameButtons7;
            mPlayerControls[6].timer = timerControl7;
            mPlayerControls[6].nomination = nominateButton7;
            mPlayerControls[6].numberLabel = numberLabel7;

            mPlayerControls[7].nameLabel = nameLabel8;
            mPlayerControls[7].buttons = gameButtons8;
            mPlayerControls[7].timer = timerControl8;
            mPlayerControls[7].nomination = nominateButton8;
            mPlayerControls[7].numberLabel = numberLabel8;

            mPlayerControls[8].nameLabel = nameLabel9;
            mPlayerControls[8].buttons = gameButtons9;
            mPlayerControls[8].timer = timerControl9;
            mPlayerControls[8].nomination = nominateButton9;
            mPlayerControls[8].numberLabel = numberLabel9;

            mPlayerControls[9].nameLabel = nameLabel10;
            mPlayerControls[9].buttons = gameButtons10;
            mPlayerControls[9].timer = timerControl10;
            mPlayerControls[9].nomination = nominateButton10;
            mPlayerControls[9].numberLabel = numberLabel10;
        }

        public GameDayControl()
        {
            InitializeComponent();
            InitPlayerControls();
            for (int i = 0; i < 10; ++i)
            {
                mPlayerControls[i].nomination.String = Properties.Resources.Nominate;
            }
            noNominateButton.String = Properties.Resources.NoNominate;
        }

        public static bool IsMyGameState(GameState state)
        {
            switch (state)
            {
                case GameState.DayStart:
                case GameState.DayFreeDiscussion:
                case GameState.DayPlayerSpeaking:
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
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                PlayerControls controls = mPlayerControls[i];

                controls.nameLabel.String = player.Name;
                controls.buttons.PlayerNum = i;
            }
        }

        protected override void OnGameStateChange()
        {
            Game game = Database.Game;
            titleLabel.Text = string.Format(Properties.Resources.DayNum, game.Round + 1);
            GamePlayer player;

            StringBuilder tipText = new StringBuilder();
            bool nominationEnabled = false;
            int initialTime = 60;
            switch (game.State)
            {
                case GameState.DayStart:
                    tipText.Append(Properties.Resources.GoodMorning);
                    tipText.Append(Properties.Resources.Space);
                    if (game.Round != 0)
                    {
                        if (game.PlayerSpeaking >= 0)
                        {
                            player = game.Players[game.PlayerSpeaking];
                            if (player.IsDummy)
                            {
                                tipText.Append(string.Format(Properties.Resources.KilledLastNight, player));
                            }
                            else
                            {
                                int nextSpeaker = game.NextPlayer(-1);
                                if (nextSpeaker == game.PlayerSpeaking)
                                {
                                    nextSpeaker = game.NextPlayer(nextSpeaker);
                                }
                                tipText.Append(string.Format(Properties.Resources.LastSpeech, player, player.IsMale ? Properties.Resources.his : Properties.Resources.her));

                                initialTime = 30;
                                nominationEnabled = !game.CurrentVoting.Canceled && game.Rules.NightKillCanNominate;
                            }
                        }
                        else
                        {
                            tipText.Append(Properties.Resources.NobodyKilled + Properties.Resources.Space);
                        }
                    }

                    tipText.Append(Properties.Resources.Space);
                    if (game.Rules.FreeRound)
                    {
                        tipText.Append(Properties.Resources.StartFreeRound);
                    }
                    else
                    {
                        tipText.Append(string.Format(Properties.Resources.FloorToNext, game.Players[game.NextPlayer(-1)]));
                    }
                    break;

                case GameState.DayFreeDiscussion:
                    tipText.Append(Properties.Resources.FreeRound);
                    tipText.Append(Properties.Resources.Space);
                    tipText.Append(string.Format(Properties.Resources.FloorToNext, game.Players[game.NextPlayer(-1)]));
                    break;

                case GameState.DayPlayerSpeaking:
                    player = game.Players[game.PlayerSpeaking];
                    if (player.IsDead)
                    {
                        switch (player.KillReason)
                        {
                            case GamePlayerKillReason.Normal:
                                tipText.Append(string.Format(Properties.Resources.PlayerKilled, player));
                                break;
                            case GamePlayerKillReason.Suicide:
                                tipText.Append(string.Format(Properties.Resources.PlayerSuisided, player));
                                break;
                            case GamePlayerKillReason.Warnings:
                                tipText.Append(string.Format(Properties.Resources.PlayerKilledByWarnings, player));
                                break;
                            case GamePlayerKillReason.KickOut:
                                tipText.Append(string.Format(Properties.Resources.PlayerKickedOut, player));
                                break;
                        }
                    }
                    else if (!player.MissesSpeech)
                    {
                        tipText.Append(string.Format(Properties.Resources.PlayerSpeaking, player));
                    }
                    else if (player.IsMale)
                    {
                        tipText.Append(string.Format(Properties.Resources.PlayerMissingSpeech, player, Properties.Resources.his, Properties.Resources.he));
                    }
                    else
                    {
                        tipText.Append(string.Format(Properties.Resources.PlayerMissingSpeech, player, Properties.Resources.her, Properties.Resources.she));
                    }

                    tipText.Append(Properties.Resources.Space);

                    {
                        int nextSpeaker = game.NextPlayer(game.PlayerSpeaking);
                        if (nextSpeaker >= 0)
                        {
                            tipText.Append(string.Format(Properties.Resources.FloorToNext, game.Players[nextSpeaker]));
                        }
                        else
                        {
                            tipText.Append(Properties.Resources.StartVoting);
                        }
                    }

                    nominationEnabled = !game.CurrentVoting.Canceled;
                    break;
            }

            for (int i = 0; i < 10; ++i)
            {
                player = game.Players[i];
                PlayerControls controls = mPlayerControls[i];

                if (i == game.PlayerSpeaking)
                {
                    controls.timer.InitialTime = initialTime;

                    SetControlState(controls.nameLabel, HIGHLIGHT, true);
                    SetControlState(controls.buttons, HIGHLIGHT, true);
                    SetControlState(controls.timer, HIGHLIGHT, !player.MissesSpeech && !player.IsDummy);
                    SetControlState(controls.nomination, HIGHLIGHT, nominationEnabled && player.IsAlive && !game.CurrentVoting.IsNominated(i));
                    SetControlState(controls.numberLabel, HIGHLIGHT, true);
                    controls.buttons.UpdatePlayer();
                }
                else if (!player.IsAlive)
                {
                    SetControlState(controls.nameLabel, DEAD, true);
                    SetControlState(controls.buttons, DEAD, false);
                    SetControlState(controls.timer, DEAD, false);
                    SetControlState(controls.nomination, DEAD, false);
                    SetControlState(controls.numberLabel, DEAD, true);
                }
                else
                {
                    SetControlState(controls.nameLabel, ALIVE, true);
                    SetControlState(controls.buttons, ALIVE, true);
                    SetControlState(controls.timer, ALIVE, false);
                    if (!nominationEnabled || player.IsDummy)
                    {
                        SetControlState(controls.nomination, ALIVE, false);
                    }
                    else if (game.CurrentVoting.IsNominated(i))
                    {
                        SetControlState(controls.nomination, HIGHLIGHT, false);
                    }
                    else
                    {
                        SetControlState(controls.nomination, ALIVE, true);
                    }
                    SetControlState(controls.numberLabel, ALIVE, true);
                    controls.buttons.UpdatePlayer();
                }
                controls.nomination.Checked = false;
            }

            if (game.CurrentNominant >= 0)
            {
                mPlayerControls[game.CurrentNominant].nomination.Checked = true;
                noNominateButton.Checked = false;
            }
            else
            {
                noNominateButton.Checked = true;
            }
            noNominatePanel.Visible = nominationEnabled;
            freeRoundTimerControl.Enabled = freeRoundTimerControl.Visible = (game.State == GameState.DayFreeDiscussion);

            tipLabel.Text = tipText.ToString();
        }

        private void nominateButton_CheckedChanged(object sender, EventArgs e)
        {
            if (Initializing)
            {
                return;
            }

            BorderRadioButton button = null;
            if (noNominateButton.IsMyEvent(sender))
            {
                button = noNominateButton;
            }
            else for (int i = 0; i < 10; ++i)
            {
                PlayerControls controls = mPlayerControls[i];
                if (controls.nomination.IsMyEvent(sender))
                {
                    button = controls.nomination;
                    break;
                }
            }

            if (button == null)
            {
                return;
            }

            if (button.Checked)
            {
                Game game = Database.Game;
                if (button == noNominateButton)
                {
                    game.CurrentNominant = -1;
                }
                else
                {
                    noNominateButton.Checked = false;
                }
                for (int i = 0; i < 10; ++i)
                {
                    PlayerControls controls = mPlayerControls[i];
                    if (controls.nomination == button)
                    {
                        game.CurrentNominant = i;
                    }
                    else if (!controls.nomination.Grayed)
                    {
                        controls.nomination.Checked = false;
                    }
                }
            }
        }
    }
}
