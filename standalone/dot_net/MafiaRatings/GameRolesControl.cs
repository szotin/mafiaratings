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
    public partial class GameRolesControl : GameControl
    {
        private struct PlayerControls
        {
            public BorderLabel nameLabel;
            public BorderComboBox roleCombo;
            public GameButtons buttons;
            public BorderLabel numberLabel;
            public BorderLabel immunityLabel;
        }

        private PlayerControls[] mPlayerControls;

        private void InitPlayerControls()
        {
            mPlayerControls = new PlayerControls[10];

            mPlayerControls[0].nameLabel = nameLabel1;
            mPlayerControls[0].roleCombo = roleCombo1;
            mPlayerControls[0].buttons = gameButtons1;
            mPlayerControls[0].numberLabel = numberLabel1;
            mPlayerControls[0].immunityLabel = immunityLabel1;

            mPlayerControls[1].nameLabel = nameLabel2;
            mPlayerControls[1].roleCombo = roleCombo2;
            mPlayerControls[1].buttons = gameButtons2;
            mPlayerControls[1].numberLabel = numberLabel2;
            mPlayerControls[1].immunityLabel = immunityLabel2;

            mPlayerControls[2].nameLabel = nameLabel3;
            mPlayerControls[2].roleCombo = roleCombo3;
            mPlayerControls[2].buttons = gameButtons3;
            mPlayerControls[2].numberLabel = numberLabel3;
            mPlayerControls[2].immunityLabel = immunityLabel3;

            mPlayerControls[3].nameLabel = nameLabel4;
            mPlayerControls[3].roleCombo = roleCombo4;
            mPlayerControls[3].buttons = gameButtons4;
            mPlayerControls[3].numberLabel = numberLabel4;
            mPlayerControls[3].immunityLabel = immunityLabel4;

            mPlayerControls[4].nameLabel = nameLabel5;
            mPlayerControls[4].roleCombo = roleCombo5;
            mPlayerControls[4].buttons = gameButtons5;
            mPlayerControls[4].numberLabel = numberLabel5;
            mPlayerControls[4].immunityLabel = immunityLabel5;

            mPlayerControls[5].nameLabel = nameLabel6;
            mPlayerControls[5].roleCombo = roleCombo6;
            mPlayerControls[5].buttons = gameButtons6;
            mPlayerControls[5].numberLabel = numberLabel6;
            mPlayerControls[5].immunityLabel = immunityLabel6;

            mPlayerControls[6].nameLabel = nameLabel7;
            mPlayerControls[6].roleCombo = roleCombo7;
            mPlayerControls[6].buttons = gameButtons7;
            mPlayerControls[6].numberLabel = numberLabel7;
            mPlayerControls[6].immunityLabel = immunityLabel7;

            mPlayerControls[7].nameLabel = nameLabel8;
            mPlayerControls[7].roleCombo = roleCombo8;
            mPlayerControls[7].buttons = gameButtons8;
            mPlayerControls[7].numberLabel = numberLabel8;
            mPlayerControls[7].immunityLabel = immunityLabel8;

            mPlayerControls[8].nameLabel = nameLabel9;
            mPlayerControls[8].roleCombo = roleCombo9;
            mPlayerControls[8].buttons = gameButtons9;
            mPlayerControls[8].numberLabel = numberLabel9;
            mPlayerControls[8].immunityLabel = immunityLabel9;

            mPlayerControls[9].nameLabel = nameLabel10;
            mPlayerControls[9].roleCombo = roleCombo10;
            mPlayerControls[9].buttons = gameButtons10;
            mPlayerControls[9].numberLabel = numberLabel10;
            mPlayerControls[9].immunityLabel = immunityLabel10;
        }

        public GameRolesControl()
        {
            InitializeComponent();
            InitPlayerControls();
            for (int i = 0; i < 10; ++i)
            {
                BorderComboBox box = mPlayerControls[i].roleCombo;
                box.Items.Clear();
                box.Items.Add("");
                box.Items.Add(Properties.Resources.mafia);
                box.Items.Add(Properties.Resources.don);
                box.Items.Add(Properties.Resources.sheriff);
            }
        }

        public static bool IsMyGameState(GameState state)
        {
            return (state == GameState.Night0Start);
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
                switch (player.Role)
                {
                    case GamePlayerRole.Don:
                        controls.roleCombo.SelectedIndex = 2;
                        break;
                    case GamePlayerRole.Mafia:
                        controls.roleCombo.SelectedIndex = 1;
                        break;
                    case GamePlayerRole.Sheriff:
                        controls.roleCombo.SelectedIndex = 3;
                        break;
                    default:
                        controls.roleCombo.SelectedIndex = 0;
                        break;
                }
                if (player.HasImmunity)
                {
                    controls.immunityLabel.String = Properties.Resources.ImmunityLower;
                }
                else
                {
                    controls.immunityLabel.String = string.Empty;
                }
                controls.buttons.PlayerNum = i;
            }
        }

        protected override void OnGameStateChange()
        {
            Game game = Database.Game;
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                PlayerControls controls = mPlayerControls[i];

                if (player.IsAlive)
                {
                    SetControlState(controls.nameLabel, ALIVE, true);
                    SetControlState(controls.immunityLabel, ALIVE, true);
                    SetControlState(controls.roleCombo, ALIVE, !player.IsDummy);
                    SetControlState(controls.numberLabel, ALIVE, true);
                    SetControlState(controls.buttons, ALIVE, true);
                    controls.buttons.UpdatePlayer();
                }
                else
                {
                    SetControlState(controls.nameLabel, DEAD, true);
                    SetControlState(controls.immunityLabel, DEAD, false);
                    SetControlState(controls.roleCombo, DEAD, false);
                    SetControlState(controls.numberLabel, DEAD, true);
                    SetControlState(controls.buttons, DEAD, false);
                }
            }
        }

        private void roleCombo_SelectedIndexChanged(object sender, EventArgs e)
        {
            if (Initializing)
            {
                return;
            }

            for (int i = 0; i < 10; ++i)
            {
                BorderComboBox box = mPlayerControls[i].roleCombo;
                if (box.IsMyEvent(sender))
                {
                    switch (box.SelectedIndex)
                    {
                        case 0:
                            Database.Game.SetPlayerRole(i, GamePlayerRole.Civilian);
                            break;
                        case 1:
                            Database.Game.SetPlayerRole(i, GamePlayerRole.Mafia);
                            break;
                        case 2:
                            Database.Game.SetPlayerRole(i, GamePlayerRole.Don);
                            break;
                        case 3:
                            Database.Game.SetPlayerRole(i, GamePlayerRole.Sheriff);
                            break;
                    }
                    break;
                }
            }

            Reload();
        }
    }
}
