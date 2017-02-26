using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using System.IO;
using MafiaRatings.GameObjects;
using System.Threading;

namespace MafiaRatings
{
    public partial class MainForm : Form
    {
        private const int APPLICATION_ID = 0;
        private const int APPLICATION_VERSION = 0;

        private GameControl mCurrentControl;
        private static MainForm mMainForm;

        private bool mGameStateChanged = false; // prevent recursion
        private void GameStateChanged()
        {
            if (mGameStateChanged)
            {
                return;
            }
            mGameStateChanged = true;
            try
            {
                if (mCurrentControl == null || !mCurrentControl.IsActive)
                {
                    GameControl newControl = null;
                    GameState gameState = Database.Game.State;

                    if (StartGameControl.IsMyGameState(gameState))
                    {
                        newControl = new StartGameControl();
                    }
                    else if (GameRolesControl.IsMyGameState(gameState))
                    {
                        newControl = new GameRolesControl();
                    }
                    else if (GameArrangeControl.IsMyGameState(gameState))
                    {
                        newControl = new GameArrangeControl();
                    }
                    else if (GameDayControl.IsMyGameState(gameState))
                    {
                        newControl = new GameDayControl();
                    }
                    else if (GameStartVotingControl.IsMyGameState(gameState))
                    {
                        newControl = new GameStartVotingControl();
                    }
                    else if (GameVotingControl.IsMyGameState(gameState))
                    {
                        newControl = new GameVotingControl();
                    }
                    else if (GameShootingControl.IsMyGameState(gameState))
                    {
                        newControl = new GameShootingControl();
                    }
                    else if (GameNightControl.IsMyGameState(gameState))
                    {
                        newControl = new GameNightControl();
                    }
                    else if (GameEndControl.IsMyGameState(gameState))
                    {
                        newControl = new GameEndControl();
                    }

                    if (newControl != null)
                    {
                        newControl.Reload();
                        newControl.Dock = DockStyle.Fill;
                        contentPanel.Controls.Add(newControl);
                    }

                    if (mCurrentControl != null)
                    {
                        contentPanel.Controls.Remove(mCurrentControl);
                        mCurrentControl.Dispose();
                    }

                    mCurrentControl = newControl;
                }

                GameControl.SetColors(toolStrip, GameControl.SYSTEM);
                GameControl.SetColors(statusStrip, GameControl.SYSTEM);
                GameControl.SetColors(buttonsPanel, GameControl.BACK);

                if (mCurrentControl != null)
                {
                    mCurrentControl.GameStateChanged();
                }

                backButton.Enabled = Database.Game.BackEnabled;
                nextButton.Enabled = Database.Game.NextEnabled;
                endButton.Visible = Database.Game.TerminateEnabled;
                loginMenuItem.Enabled = optionsButton.Enabled = Database.Game.State == GameState.NotStarted;
                refreshButton.Enabled = Database.Gate.Connected && Database.Game.State == GameState.NotStarted;
            }
            finally
            {
                mGameStateChanged = false;
            }
        }

        public MainForm()
        {
            if (Program.Lang != null)
            {
                Thread.CurrentThread.CurrentUICulture = new System.Globalization.CultureInfo(Program.Lang);
            }

            InitializeComponent();

            Database.DataDir =
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData) +
                Path.DirectorySeparatorChar +
                "MafiaRatings";

            Database.Gate.OnConnectionStatusChange += new Gate.ConnectionStatusChanged(ConnectionStatusChanged);
            Database.Game.OnGameAction += new Game.GameActionListener(GameStateChanged);

            if (Properties.Resources.Lang == "ru")
            {
                Database.Gate.Lang = Language.RUSSIAN;
            }
            else
            {
                Database.Gate.Lang = Language.ENGLISH;
            }


            Text = Database.Club.Name;

#if DEBUG
            lastResponseButton.Visible = true;
#else
            lastResponseButton.Visible = false;
#endif
            mMainForm = this;
        }

        private bool Synchronize()
        {
            if (Database.CanSynchronize)
            {
                if (Database.Gate.Clubs.Length > 1)
                {
                    ClubsDialog dialog = new ClubsDialog();
                    dialog.ShowDialog();
                    Database.Gate.Club = dialog.Club;
                }
                if (mCurrentControl != null)
                {
                    mCurrentControl.Reload();
                }
                return true;
            }
            return false;
        }

        private void MainForm_Load(object sender, EventArgs e)
        {
            if (!DesignMode)
            {
                if (!Database.ConnectOnStartup || !Connect())
                {
                    ConnectionStatusChanged(Gate.ConnectionStatus.Disconnected);
                }
                GameStateChanged();
            }
        }

        private void MainForm_FormClosed(object sender, FormClosedEventArgs e)
        {
            ProgressDialog.Execute(delegate()
            {
                Database.Gate.Logout();
            });
        }

        private void refreshButton_Click(object sender, EventArgs e)
        {
            Synchronize();
        }

        private void lastResponseButton_Click(object sender, EventArgs e)
        {
#if DEBUG
            LastServerResponseDialog dialog = new LastServerResponseDialog(Database.Gate);
            dialog.ShowDialog();
#endif
        }

        private void backButton_Click(object sender, EventArgs e)
        {
            try
            {
                Database.Game.Back();
            }
            catch (Exception ex)
            {
                MessageBox.Show(ex.Message, Properties.Resources.GameErr, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private void nextButton_Click(object sender, EventArgs e)
        {
            try
            {
                ProgressDialog.Execute(delegate()
                {
                    Database.Game.Next();
                });
            }
            catch (Exception ex)
            {
                MessageBox.Show(ex.Message, Properties.Resources.GameErr, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private void ConnectionStatusChanged(Gate.ConnectionStatus oldStatus)
        {
            switch (Database.Gate.Status)
            {
                case Gate.ConnectionStatus.Connected:
                    connectMenuItem.Enabled = false;
                    disconnectMenuItem.Enabled = true;
                    statusLabel.Text = String.Format(Properties.Resources.Connected, Database.Gate.UserName, Database.Gate.HostUrl);
                    break;
                case Gate.ConnectionStatus.Disconnected:
                    connectMenuItem.Enabled = true;
                    disconnectMenuItem.Enabled = false;
                    statusLabel.Text = Properties.Resources.Offline;
                    break;
                case Gate.ConnectionStatus.NotAvailable:
                    connectMenuItem.Enabled = true;
                    disconnectMenuItem.Enabled = true;
                    statusLabel.Text = Properties.Resources.NoServer;
                    break;
            }
        }

        public static bool Connect()
        {
            try
            {
                bool result = false;
                ProgressDialog.Execute(delegate()
                {
                    result = Database.Gate.Login();
                });

                if (!result)
                {
                    return Login();
                }
                Database.ConnectOnStartup = true;
                return mMainForm.Synchronize();
            }
            catch (Exception ex)
            {
                if (mMainForm.mCurrentControl != null)
                {
                    mMainForm.mCurrentControl.Reload();
                }
                mMainForm.GameStateChanged();
                MessageBox.Show(ex.Message, Properties.Resources.LoginErr, MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
            return false;
        }

        public static bool Disconnect()
        {
            try
            {
                ProgressDialog.Execute(delegate()
                {
                    Database.Gate.Logout();
                });
            }
            catch (Exception ex)
            {
                MessageBox.Show(ex.Message, Properties.Resources.LoginErr, MessageBoxButtons.OK, MessageBoxIcon.Error);
                return false;
            }
            Database.ConnectOnStartup = false;
            return true;
        }

        public static bool Login()
        {
            LoginDialog dialog = new LoginDialog();
            switch (dialog.ShowDialog())
            {
                case DialogResult.OK:
                    try
                    {
                        bool result = true;
                        ProgressDialog.Execute(delegate()
                        {
                            result = Database.Gate.Login(dialog.Login, dialog.Password, dialog.SavePassword);
                        });

                        if (!result)
                        {
                            MessageBox.Show(Properties.Resources.ErrSavePwd, Properties.Resources.LoginErr, MessageBoxButtons.OK, MessageBoxIcon.Warning);
                        }

                        Database.ConnectOnStartup = true;
                        return mMainForm.Synchronize();
                    }
                    catch (Exception ex)
                    {
                        MessageBox.Show(ex.Message, Properties.Resources.LoginErr, MessageBoxButtons.OK, MessageBoxIcon.Error);
                    }
                    break;

                case DialogResult.Abort:
                    Application.Exit();
                    break;
            }
            return false;
        }

        private void connectMenuItem_Click(object sender, EventArgs e)
        {
            Connect();
        }

        private void disconnectMenuItem_Click(object sender, EventArgs e)
        {
            Disconnect();
        }

        private void loginMenuItem_Click(object sender, EventArgs e)
        {
            Login();
        }

        private void endButton_Click(object sender, EventArgs e)
        {
            Database.Game.Terminate();
        }

        private void optionsButton_Click(object sender, EventArgs e)
        {
            OptionsDialog dialog = new OptionsDialog();
            if (dialog.ShowDialog() == System.Windows.Forms.DialogResult.OK)
            {
                if (Database.Gate.HostUrl != dialog.HostUrl)
                {
                    Database.Gate.HostUrl = dialog.HostUrl;
                    Login();
                }
            }
        }
    }
}
