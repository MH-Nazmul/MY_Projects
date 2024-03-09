
package mhproject;

import java.awt.Color;
import javax.swing.BorderFactory;
import javax.swing.ImageIcon;
import javax.swing.JButton;
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.SwingUtilities;


public class Myframe extends JFrame {
    void run_clock()
        {
             SwingUtilities.invokeLater(()->{
        
            Clock app = new Clock();
            app.setVisible(true);
            
        });
        }
    void run_cal()
    {
        Calculator1 calc = new Calculator1();
    } 
    void run_stop()
    {
        Stop_watch frame = new Stop_watch();
        frame.setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        frame.setSize(300, 250);
        frame.setVisible(true);
    } 
    void run_cgcal()
    {
       java.awt.EventQueue.invokeLater(() -> {
            new cgcal().setVisible(true);
        });
    }
    JLabel label,project_name,team_name;
    Myframe(){
        
        ImageIcon image=new ImageIcon("/home/mhn/Documents/MHProject/src/mhproject/emptyman.jpg");
        label=new JLabel();
        label.setIcon(image);
        label.setBounds(65,275,450,450);
        //label.setVisible(true);
        
        project_name=new JLabel("");
        project_name.setText("MultyWidgets store");
        project_name.setBounds(220,10,500,300);
        project_name.setVisible(true);
        
        team_name=new JLabel("");
        team_name.setText("PSTU EmptyBit");
        team_name.setBounds(240,185,500,300);
        team_name.setVisible(true);
        
        JButton clock = new JButton();
        clock.setBounds(200,200,100,50);
        clock.setText("Clock");
        clock.addActionListener(e -> run_clock());
        clock.setFocusable(false);
        clock.setForeground(Color.WHITE);
        clock.setBackground(Color.GRAY);
        clock.setBorder(BorderFactory.createEtchedBorder());
        
        JButton calculator = new JButton();
        calculator.setBounds(300,200,100,50);
        calculator.setText("Calculator");
        calculator.addActionListener(e -> run_cal());
        calculator.setFocusable(false);
        calculator.setForeground(Color.WHITE);
        calculator.setBackground(Color.GRAY);
        calculator.setBorder(BorderFactory.createEtchedBorder());
        
        JButton stop = new JButton();
         stop.setBounds(400,200,100,50);
         stop.setText("Stopwatch");
         stop.addActionListener(e -> run_stop());
         stop.setFocusable(false);
         stop.setForeground(Color.WHITE);
         stop.setBackground(Color.GRAY);
         stop.setBorder(BorderFactory.createEtchedBorder());
         
         JButton cgcal = new JButton();
        cgcal.setBounds(80,200,120,50);
        cgcal.setText("CGPA Calculator");
        cgcal.addActionListener(e -> run_cgcal());
        cgcal.setFocusable(false);
        cgcal.setForeground(Color.WHITE);
        cgcal.setBackground(Color.GRAY);
        cgcal.setBorder(BorderFactory.createEtchedBorder());
        
        this.setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        this.setLayout(null);
        this.setSize(600,800);
        this.setVisible(true);
        this.add(clock);
        this.add(calculator);
        this.add(stop);
        this.add(cgcal);
        this.add(project_name);
        this.add(team_name);
        this.add(label);
        
        //this.setName("PSTU_EmptyBit");
        
        
    }
    
}
class active_project{
     public static void main(String[] args) {
       Myframe frame =new Myframe();
    
    }
}
