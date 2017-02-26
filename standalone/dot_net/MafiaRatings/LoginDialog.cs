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
    public partial class LoginDialog : Form
    {
        public LoginDialog()
        {
            InitializeComponent();
            loginTextBox.Text = Database.UserName;
            loginTextBox.Enabled = Database.Game.State == GameState.NotStarted;
        }

        private void loginTextBox_TextChanged(object sender, EventArgs e)
        {
            okButton.Enabled = (loginTextBox.Text.Length != 0);
        }

        public string Login
        {
            get { return loginTextBox.Text; }
        }

        public string Password
        {
            get { return passwordTextBox.Text; }
        }

        public bool SavePassword
        {
            get { return savePasswordCheckBox.Checked; }
        }
    }
}
