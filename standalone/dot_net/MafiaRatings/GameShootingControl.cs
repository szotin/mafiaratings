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
    public partial class GameShootingControl : GameControl
    {
        private struct PlayerControls
        {
            public BorderLabel nameLabel;
            public BorderComboBox shot;
            public GameButtons buttons;
            public BorderLabel numberLabel;
        }

        private class Player
        {
            public GamePlayer player;

            public Player(GamePlayer player)
            {
                this.player = player;
            }

            public override string ToString()
            {
                return (player.Number + 1).ToString() + " - " + player.ToString();
            }
        }

        private PlayerControls[] mPlayerControls;

        private void InitPlayerControls()
        {
            mPlayerControls = new PlayerControls[10];

            mPlayerControls[0].nameLabel = nameLabel1;
            mPlayerControls[0].shot = shotComboBox1;
            mPlayerControls[0].buttons = gameButtons1;
            mPlayerControls[0].numberLabel = numberLabel1;

            mPlayerControls[1].nameLabel = nameLabel2;
            mPlayerControls[1].shot = shotComboBox2;
            mPlayerControls[1].buttons = gameButtons2;
            mPlayerControls[1].numberLabel = numberLabel2;

            mPlayerControls[2].nameLabel = nameLabel3;
            mPlayerControls[2].shot = shotComboBox3;
            mPlayerControls[2].buttons = gameButtons3;
            mPlayerControls[2].numberLabel = numberLabel3;

            mPlayerControls[3].nameLabel = nameLabel4;
            mPlayerControls[3].shot = shotComboBox4;
            mPlayerControls[3].buttons = gameButtons4;
            mPlayerControls[3].numberLabel = numberLabel4;

            mPlayerControls[4].nameLabel = nameLabel5;
            mPlayerControls[4].shot = shotComboBox5;
            mPlayerControls[4].buttons = gameButtons5;
            mPlayerControls[4].numberLabel = numberLabel5;

            mPlayerControls[5].nameLabel = nameLabel6;
            mPlayerControls[5].shot = shotComboBox6;
            mPlayerControls[5].buttons = gameButtons6;
            mPlayerControls[5].numberLabel = numberLabel6;

            mPlayerControls[6].nameLabel = nameLabel7;
            mPlayerControls[6].shot = shotComboBox7;
            mPlayerControls[6].buttons = gameButtons7;
            mPlayerControls[6].numberLabel = numberLabel7;

            mPlayerControls[7].nameLabel = nameLabel8;
            mPlayerControls[7].shot = shotComboBox8;
            mPlayerControls[7].buttons = gameButtons8;
            mPlayerControls[7].numberLabel = numberLabel8;

            mPlayerControls[8].nameLabel = nameLabel9;
            mPlayerControls[8].shot = shotComboBox9;
            mPlayerControls[8].buttons = gameButtons9;
            mPlayerControls[8].numberLabel = numberLabel9;

            mPlayerControls[9].nameLabel = nameLabel10;
            mPlayerControls[9].shot = shotComboBox10;
            mPlayerControls[9].buttons = gameButtons10;
            mPlayerControls[9].numberLabel = numberLabel10;
        }

        public GameShootingControl()
        {
            InitializeComponent();
            InitPlayerControls();
        }

        public static bool IsMyGameState(GameState state)
        {
            return (state == GameState.NightStart || state == GameState.NightShooting);
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
            titleLabel.Text = string.Format(Properties.Resources.NightNum, game.Round + 1);
        }

        protected override void OnGameStateChange()
        {
            Game game = Database.Game;

            bool shooting = false;
            switch (game.State)
            {
                case GameState.NightStart:
                    tipLabel.Text = Properties.Resources.NightFalling;
                    break;

                case GameState.NightShooting:
                    tipLabel.Text = Properties.Resources.EnterShooting;
                    shooting = true;
                    break;
            }

            Player[] players = new Player[game.NumPlayers];
            int index = 0;
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                if (player.IsAlive)
                {
                    players[index++] = new Player(player);
                }
            }

            GamePlayer dummyPlayer = game.GetDummyPlayer();
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                PlayerControls controls = mPlayerControls[i];

                controls.shot.Items.Clear();
                if (player.IsAlive)
                {
                    if (player.Role == GamePlayerRole.Mafia || player.Role == GamePlayerRole.Don)
                    {
                        if (dummyPlayer == null)
                        {
                            int shootingTo = game.GetNightShot(i);
                            if (shootingTo < 0)
                            {
                                controls.shot.SelectedIndex = controls.shot.Items.Add(string.Empty);
                            }
                            else
                            {
                                controls.shot.Items.Add(string.Empty);
                            }
                            foreach (Player p in players)
                            {
                                index = controls.shot.Items.Add(p);
                                if (shootingTo >= 0)
                                {
                                    if (shootingTo == p.player.Number)
                                    {
                                        controls.shot.SelectedIndex = index;
                                    }
                                }
                                else if (p.player.Arranged == game.Round)
                                {
                                    shootingTo = p.player.Number;
                                    controls.shot.SelectedIndex = index;
                                }
                            }
                            game.NightShot(i, shootingTo);
                        }
                        else
                        {
                            controls.shot.SelectedIndex = controls.shot.Items.Add(dummyPlayer);
                        }
                        SetControlState(controls.shot, ALIVE, shooting);
                    }
                    else
                    {
                        SetControlState(controls.shot, ALIVE, false);
                    }

                    SetControlState(controls.nameLabel, ALIVE, true);
                    SetControlState(controls.numberLabel, ALIVE, true);
                    SetControlState(controls.buttons, ALIVE, true);
                    controls.buttons.UpdatePlayer();
                }
                else
                {
                    SetControlState(controls.nameLabel, DEAD, true);
                    SetControlState(controls.shot, DEAD, false);
                    SetControlState(controls.numberLabel, DEAD, true);
                    SetControlState(controls.buttons, DEAD, false);
                }
            }
        }

        private void shotComboBox_SelectedIndexChanged(object sender, EventArgs e)
        {
            if (Initializing)
            {
                return;
            }

            Game game = Database.Game;
            for (int i = 0; i < 10; ++i)
            {
                PlayerControls controls = mPlayerControls[i];
                if (controls.shot.IsMyEvent(sender))
                {
                    Player player = controls.shot.SelectedItem as Player;
                    game.NightShot(i, player != null ? player.player.Number : -1);
                    break;
                }
            }
        }
    }
}
