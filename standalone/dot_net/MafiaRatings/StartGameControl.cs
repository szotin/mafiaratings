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
    public partial class StartGameControl : GameControl
    {
        private struct PlayerControls
        {
            public ComboBox player;
            public Button register;
            public Button registerNew;
        }

        private PlayerControls[] mPlayerControls;

        private void InitPlayerControls()
        {
            mPlayerControls = new PlayerControls[10];

            mPlayerControls[0].player = playerCombo1;
            mPlayerControls[0].register = registerButton1;
            mPlayerControls[0].registerNew = registerNewButton1;

            mPlayerControls[1].player = playerCombo2;
            mPlayerControls[1].register = registerButton2;
            mPlayerControls[1].registerNew = registerNewButton2;

            mPlayerControls[2].player = playerCombo3;
            mPlayerControls[2].register = registerButton3;
            mPlayerControls[2].registerNew = registerNewButton3;

            mPlayerControls[3].player = playerCombo4;
            mPlayerControls[3].register = registerButton4;
            mPlayerControls[3].registerNew = registerNewButton4;

            mPlayerControls[4].player = playerCombo5;
            mPlayerControls[4].register = registerButton5;
            mPlayerControls[4].registerNew = registerNewButton5;

            mPlayerControls[5].player = playerCombo6;
            mPlayerControls[5].register = registerButton6;
            mPlayerControls[5].registerNew = registerNewButton6;

            mPlayerControls[6].player = playerCombo7;
            mPlayerControls[6].register = registerButton7;
            mPlayerControls[6].registerNew = registerNewButton7;

            mPlayerControls[7].player = playerCombo8;
            mPlayerControls[7].register = registerButton8;
            mPlayerControls[7].registerNew = registerNewButton8;

            mPlayerControls[8].player = playerCombo9;
            mPlayerControls[8].register = registerButton9;
            mPlayerControls[8].registerNew = registerNewButton9;

            mPlayerControls[9].player = playerCombo10;
            mPlayerControls[9].register = registerButton10;
            mPlayerControls[9].registerNew = registerNewButton10;
        }

        public StartGameControl()
        {
            InitializeComponent();
            InitPlayerControls();
        }

        private void eventsComboBox_SelectedIndexChanged(object sender, EventArgs e)
        {
            Party party = eventsComboBox.SelectedItem as Party;
            if (party != null)
            {
                Database.Game.PartyId = party.Id;

                List<Registration> registraions = party.QueryPlayers();
                for (int i = 0; i < 10; ++i)
                {
                    ComboBox box = mPlayerControls[i].player;
                    GamePlayer player = Database.Game.Players[i];
                    int selectedIndex = 0;

                    box.Items.Clear();
                    box.Items.Add(string.Empty);

                    foreach (Registration reg in registraions)
                    {
                        if (player.UserId == reg.UserId)
                        {
                            selectedIndex = box.Items.Add(reg);
                        }
                        else
                        {
                            box.Items.Add(reg);
                        }
                    }
                    box.SelectedIndex = selectedIndex;
                }

                if (party.AllCanModerate)
                {
                    registraions = party.QueryModerators();
                    int selectedIndex = 0;

                    moderComboBox.Items.Clear();
                    moderComboBox.Items.Add(string.Empty);

                    foreach (Registration reg in registraions)
                    {
                        if (reg.UserId > 0)
                        {
                            if (Database.Game.ModeratorId == reg.UserId)
                            {
                                selectedIndex = moderComboBox.Items.Add(reg);
                            }
                            else
                            {
                                moderComboBox.Items.Add(reg);
                            }
                        }
                    }
                    moderComboBox.SelectedIndex = selectedIndex;
                }
                else
                {
                    moderComboBox.SelectedIndex = moderComboBox.Items.Add(Database.UserName);
                    Database.Game.ModeratorId = Database.UserId;
                }

                int langsCount = Language.GetLangsCount(party.Languages);
                if (langsCount == 1)
                {
                    langComboBox.Items.Clear();
                    langComboBox.Items.Add(new Language(party.Languages));
                    langComboBox.Visible = false;
                    langLabel.Visible = false;
                }
                else
                {
                    langComboBox.Items.Clear();
                    langComboBox.Items.Add(new Language(Language.NO));
                    int lang = Language.NO;
                    while ((lang = Language.GetNextLang(lang, party.Languages)) != Language.NO)
                    {
                        langComboBox.Items.Add(new Language(lang));
                    }
                    langComboBox.Visible = true;
                    langLabel.Visible = true;
                }
                langComboBox.SelectedIndex = 0;

                rulesComboBox.SelectedIndex = Database.Rules.RuleIndex(party.RulesId);
            }
            SetEnables();
        }

        private void comboBox_SelectedIndexChanged(object sender, EventArgs e)
        {
            if (Initializing)
            {
                return;
            }

            ComboBox senderBox = sender as ComboBox;
            if (senderBox == null)
            {
                return;
            }

            Registration reg = senderBox.SelectedItem as Registration;
            try
            {
                if (senderBox == moderComboBox)
                {
                    Database.Game.SetModerator(reg);
                }
                else
                {
                    for (int i = 0; i < 10; ++i)
                    {
                        if (senderBox == mPlayerControls[i].player)
                        {
                            Database.Game.SetPlayer(i, reg);
                            break;
                        }
                    }
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show(ex.Message, Properties.Resources.Error, MessageBoxButtons.OK, MessageBoxIcon.Error);
                reg = null;
                senderBox.SelectedIndex = 0;
            }

            if (reg != null)
            {
                Registration r;
                for (int i = 0; i < 10; ++i)
                {
                    ComboBox box = mPlayerControls[i].player;
                    if (box == senderBox)
                    {
                        continue;
                    }

                    r = box.SelectedItem as Registration;
                    if (r != null && r.UserId == reg.UserId)
                    {
                        box.SelectedIndex = 0;
                    }
                }

                if (senderBox != moderComboBox)
                {
                    r = moderComboBox.SelectedItem as Registration;
                    if (r != null && r.UserId == reg.UserId)
                    {
                        moderComboBox.SelectedIndex = 0;
                    }
                }
            }
        }

        private ComboBox GetAssociatedCombo(object sender)
        {
            Button button = (Button)sender;
            ComboBox box = null;
            if (button.Tag != null)
            {
                try
                {
                    int num = Convert.ToInt32(button.Tag);
                    if (num >= 0 && num < 10)
                    {
                        box = mPlayerControls[num].player;
                    }
                    else
                    {
                        box = moderComboBox;
                    }
                }
                catch (Exception)
                {
                }
            }
            return box;
        }

        private void registerButton_Click(object sender, EventArgs e)
        {
            ComboBox currentBox = GetAssociatedCombo(sender);
            Party party = (Party) eventsComboBox.SelectedItem;
            if (!Database.Gate.Connected && party.PasswordRequired)
            {
                if (
                    MessageBox.Show(Properties.Resources.ConnectToReg, Properties.Resources.NoConnection, MessageBoxButtons.OKCancel) != DialogResult.OK ||
                    !MainForm.Connect())
                {
                    return;
                }
            }

            try
            {
                RegisterDialog dialog = new RegisterDialog(party);
                while (dialog.ShowDialog() == DialogResult.OK)
                {
                    ProgressDialog.Execute(delegate()
                    {
                        Registration reg = party.RegisterUser(dialog.User.User, dialog.Nick, dialog.Duration, dialog.Password, dialog.JoinClub);
                        if (reg.CanPlay)
                        {
                            foreach (PlayerControls controls in mPlayerControls)
                            {
                                if (controls.player == currentBox)
                                {
                                    controls.player.SelectedIndex = controls.player.Items.Add(reg);
                                }
                                else
                                {
                                    controls.player.Items.Add(reg);
                                }
                            }
                        }
                        if (reg.CanModerate)
                        {
                            if (moderComboBox == currentBox)
                            {
                                moderComboBox.SelectedIndex = moderComboBox.Items.Add(reg);
                            }
                            else
                            {
                                moderComboBox.Items.Add(reg);
                            }
                        }
                    });
                    break;
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show(ex.Message, Properties.Resources.ErrRegister, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private void langComboBox_SelectedIndexChanged(object sender, EventArgs e)
        {
            Database.Game.Lang = ((Language)langComboBox.SelectedItem).Lang;
        }

        private void registerNewButton_Click(object sender, EventArgs e)
        {
            Party party = Database.Parties[Database.Game.PartyId];
            if (party == null)
            {
                return;
            }

            if (!Database.Gate.Connected)
            {
                if (
                    MessageBox.Show(Properties.Resources.ConnectToCreate, Properties.Resources.NoConnection, MessageBoxButtons.OKCancel) != DialogResult.OK ||
                    !MainForm.Connect())
                {
                    return;
                }
            }

            ComboBox currentBox = GetAssociatedCombo(sender);
            RegisterNewDialog dialog = new RegisterNewDialog(party);
            while (dialog.ShowDialog() == DialogResult.OK)
            {
                try
                {
                    ProgressDialog.Execute(delegate()
                    {
                        Registration reg = party.RegisterNewUser(dialog.UserName, dialog.Email, dialog.Gender, dialog.NickName, dialog.Duration);
                        foreach (PlayerControls controls in mPlayerControls)
                        {
                            if (controls.player == currentBox)
                            {
                                controls.player.SelectedIndex = controls.player.Items.Add(reg);
                            }
                            else
                            {
                                controls.player.Items.Add(reg);
                            }
                        }
                    });
                    break;
                }
                catch (Exception ex)
                {
                    MessageBox.Show(ex.Message, Properties.Resources.ErrRegister, MessageBoxButtons.OK, MessageBoxIcon.Error);
                }
            }
        }

        public static bool IsMyGameState(GameState state)
        {
            return (state == GameState.NotStarted);
        }

        public override bool IsActive
        {
            get
            {
                return IsMyGameState(Database.Game.State);
            }
        }

        private void SetEnables()
        {
            Party party = eventsComboBox.SelectedItem as Party;

            bool enabled = (party != null);
            foreach (PlayerControls controls in mPlayerControls)
            {
                controls.player.Enabled = enabled;
                controls.register.Enabled = enabled;
                controls.registerNew.Enabled = enabled;
            }

            eventsComboBox.Enabled = enabled;
            langComboBox.Enabled = enabled;
            moderRegisterButton.Enabled = moderComboBox.Enabled = enabled && party.AllCanModerate;
        }

        protected override void OnReload()
        {
            try
            {
                ProgressDialog.Execute(delegate()
                {
                    Database.Synchronize();
                });

                rulesComboBox.Items.Clear();
                foreach (GameRules rules in Database.Rules)
                {
                    rulesComboBox.Items.Add(rules);
                }

                List<Party> parties = Database.Parties.Query(PartyList.PRESENT);
                eventsComboBox.Items.Clear();

                if (parties != null)
                {
                    int selectedIndex = -1;
                    foreach (Party party in parties)
                    {
                        if (party.Id == Database.Game.PartyId)
                        {
                            selectedIndex = eventsComboBox.Items.Add(party);
                        }
                        else
                        {
                            eventsComboBox.Items.Add(party);
                        }
                    }
                    if (selectedIndex < 0)
                    {
                        selectedIndex = eventsComboBox.Items.Count - 1;
                    }
                    if (selectedIndex >= 0 && eventsComboBox.Items.Count > selectedIndex)
                    {
                        eventsComboBox.SelectedIndex = selectedIndex;
                    }
                }

                SetEnables();
            }
            catch (Exception ex)
            {
                MessageBox.Show(ex.Message, Properties.Resources.ErrLoadData, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private void rulesComboBox_SelectedIndexChanged(object sender, EventArgs e)
        {
            Database.Game.Rules = rulesComboBox.SelectedItem as GameRules;
        }
    }
}
