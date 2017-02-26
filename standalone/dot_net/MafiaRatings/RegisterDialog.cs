using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using MafiaRatings.GameObjects;

namespace MafiaRatings
{
    public partial class RegisterDialog : Form
    {
        private void UserSelectionChanged(bool selected)
        {
            nextButton.Enabled = selected;
        }

        private void UserSelected(UserRef user)
        {
            selectUserControl.Visible = false;
            registerControl.Visible = true;
            backButton.Enabled = true;
            nextButton.Text = Properties.Resources.Register;
            nextButton.Enabled = true;
            nextButton.DialogResult = System.Windows.Forms.DialogResult.OK;

            registerControl.User = user;
        }

        public RegisterDialog(Party party)
        {
            InitializeComponent();

            selectUserControl.OnSelectionChanged = UserSelectionChanged;
            selectUserControl.OnUserSelected = UserSelected;
            selectUserControl.Party = party;
            registerControl.Party = party;
        }

        private void nextButton_Click(object sender, EventArgs e)
        {
            UserRef user = selectUserControl.SelectedUser;
            if (user != null)
            {
                UserSelected(user);
            }
        }

        private void backButton_Click(object sender, EventArgs e)
        {
            selectUserControl.Visible = true;
            registerControl.Visible = false;
            backButton.Enabled = false;
            nextButton.Text = Properties.Resources.Next;
            nextButton.Enabled = true;
            nextButton.DialogResult = System.Windows.Forms.DialogResult.None;
        }

        public string Nick
        {
            get { return registerControl.Nick; }
        }

        public int Duration
        {
            get { return registerControl.Duration; }
        }

        public string Password
        {
            get { return registerControl.Password; }
        }

        public UserRef User
        {
            get { return registerControl.User; }
        }

        public bool JoinClub
        {
            get { return registerControl.JoinClub; }
        }
    }
}
