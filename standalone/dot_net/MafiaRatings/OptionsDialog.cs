using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Windows.Forms;

namespace MafiaRatings
{
    public partial class OptionsDialog : Form
    {
        public OptionsDialog()
        {
            InitializeComponent();
            hostTextBox.Text = GameObjects.Database.Gate.HostUrl;
        }

        private void defHostButton_Click(object sender, EventArgs e)
        {
            hostTextBox.Text = GameObjects.Gate.DEFAULT_HOST_URL;
        }

        public string HostUrl
        {
            get { return hostTextBox.Text; }
            set { hostTextBox.Text = value; }
        }
    }
}
