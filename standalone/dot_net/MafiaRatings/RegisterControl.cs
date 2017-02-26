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
    public partial class RegisterControl : UserControl
    {
        private UserRef mUser;
        private Party mParty;

        private void ReadUser()
        {
            if (mUser != null)
            {
                nickComboBox.Items.Clear();

                User user = mUser.User;
                userNameLabel.Text = user.Name;
                bool noUserName = true;
                foreach (UserNick nick in user.Nicks)
                {
                    nickComboBox.Items.Add(nick);
                    if (noUserName && string.Compare(nick.Nick, user.Name, true) == 0)
                    {
                        noUserName = false;
                    }
                }
                if (noUserName)
                {
                    nickComboBox.Items.Add(new UserNick(user.Name));
                }

                if (nickComboBox.Items.Count > 0)
                {
                    nickComboBox.SelectedIndex = 0;
                }

                joinClubCheckBox.Checked = joinClubCheckBox.Visible = !user.IsClubMember;
                joinClubCheckBox.Text = string.Format(Properties.Resources.JoinClub, user.Name, Database.Club.Name, user.IsMale ? Properties.Resources.he : Properties.Resources.she);
            }
        }

        public RegisterControl()
        {
            InitializeComponent();
        }

        public UserRef User
        {
            get { return mUser; }
            set
            {
                if (mUser != value)
                {
                    mUser = value;
                    ReadUser();
                }
            }
        }

        public Party Party
        {
            get { return mParty; }
            set
            {
                mParty = value;
                if (mParty == null)
                {
                    passwordTextBox.Enabled = false;
                    durationComboBox.Enabled = false;
                    return;
                }

                passwordTextBox.Enabled = value.PasswordRequired;
                if (value.Id <= 0)
                {
                    durationComboBox.Enabled = true;
                    durationComboBox.SelectedIndex = 5;
                }
                else
                {
                    durationComboBox.Enabled = false;
                }
            }
        }

        public string Nick
        {
            get { return nickComboBox.Text; }
        }

        public int Duration
        {
            get { return (durationComboBox.SelectedIndex + 1) * 3600; }
        }

        public string Password
        {
            get { return passwordTextBox.Text; }
        }

        public bool JoinClub
        {
            get { return joinClubCheckBox.Checked; }
        }
    }
}
