using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using System.Text.RegularExpressions;
using MafiaRatings.GameObjects;

namespace MafiaRatings
{
    public partial class RegisterNewDialog : Form
    {
        private bool mLockNickName;

        public RegisterNewDialog(Party eventInfo)
        {
            InitializeComponent();
            mLockNickName = false;
            int langs = eventInfo.Languages;
            if (eventInfo.Id <= 0)
            {
                durationComboBox.Enabled = true;
                durationComboBox.SelectedIndex = 5;
            }
            else
            {
                durationComboBox.Enabled = false;
            }
            onlineTestCheckBox_CheckedChanged(this, null);
        }

        private bool isOkEnabled(bool nameChanged)
        {
            string nameText = nameTextBox.Text;
            if (nameText.Length == 0)
            {
                statusLabel.Text = Properties.Resources.EnterUserName;
                return false;
            }
            else if (nameChanged && onlineTestCheckBox.Checked)
            {
                try
                {
                    ProgressDialog.Execute(delegate()
                    {
                        Gate.Request request = new Gate.Request(Database.Gate, Gate.Request.Code.CheckNewUserName);
                        request.Add(nameText);
                        request.Send();
                    });
                }
                catch (Exception ex)
                {
                    statusLabel.Text = ex.Message;
                    return false;
                }
            }

            string emailText = emailTextBox.Text;
            if (emailText.Length != 0 && emailText.IndexOf('@') < 0)
            {
                statusLabel.Text = string.Format(Properties.Resources.InvalidEmail, emailText);
                return false;
            }

            statusLabel.Text = string.Empty;
            return true;
        }

        private void RegisterNewDialog_Load(object sender, EventArgs e)
        {
            okButton.Enabled = isOkEnabled(false);
        }

        private void nameTextBox_TextChanged(object sender, EventArgs e)
        {
            okButton.Enabled = isOkEnabled(true);
            if (!mLockNickName)
            {
                nickTextBox.Text = nameTextBox.Text;
            }
        }

        private void onlineTestCheckBox_CheckedChanged(object sender, EventArgs e)
        {
            if (onlineTestCheckBox.Checked)
            {
                onlineTestCheckBox.ImageIndex = 1;
                toolTip.SetToolTip(onlineTestCheckBox, Properties.Resources.OnlineNameCheckOff);
                okButton.Enabled = isOkEnabled(true);
            }
            else
            {
                onlineTestCheckBox.ImageIndex = 0;
                toolTip.SetToolTip(onlineTestCheckBox, Properties.Resources.OnlineNameCheckOn);
                okButton.Enabled = isOkEnabled(false);
            }
        }

        private void nickTextBox_TextChanged(object sender, EventArgs e)
        {
            if (nickTextBox.Text.Length == 0)
            {
                nickTextBox.Text = nameTextBox.Text;
                mLockNickName = false;
            }
            else
            {
                mLockNickName = (nickTextBox.Text != nameTextBox.Text);
            }
        }

        public string UserName
        {
            get { return nameTextBox.Text; }
        }

        public int Gender
        {
            get
            {
                if (maleRadioButton.Checked)
                {
                    return User.MALE;
                }
                return User.FEMALE;
            }
        }

        public string Email
        {
            get { return emailTextBox.Text; }
        }

        public string NickName
        {
            get { return nickTextBox.Text; }
        }

        public int Duration
        {
            get { return (durationComboBox.SelectedIndex + 1) * 3600; }
        }
    }
}
